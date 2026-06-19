<?php declare(strict_types = 1);

namespace Portiny\RabbitMQ\Client;

use Bunny\Client;
use Bunny\Exception\ClientException;
use React\Promise\PromiseInterface;

/**
 * Bunny client that enables kernel TCP keepalive on the connection socket.
 *
 * Long-lived AMQP consumers behind an L4 load balancer (e.g. a public RabbitMQ endpoint
 * fronted by a cloud LB) lose their connection whenever it stays idle longer than the LB's
 * TCP idle timeout. A mostly-idle consumer, or a single message handler that blocks for
 * hours, sends no AMQP frames, so the LB silently drops the connection and the next socket
 * operation fails with "Broken pipe or closed connection".
 *
 * AMQP heartbeats do not cover this case: Bunny is synchronous, so while a handler blocks no
 * heartbeat frame is sent, and the heartbeat cannot be lowered without the broker killing
 * long-running jobs. Kernel TCP keepalive runs in the OS independently of PHP execution and
 * keeps every hop (including the LB) warm during both idle and blocking periods.
 *
 * SO_KEEPALIVE must be set on the raw TCP socket. socket_import_stream() fails once the stream
 * is encrypted, so for TLS connections the socket is opened over plain TCP, the keepalive
 * options are applied, and only then is TLS negotiated on the same, now keepalive-enabled,
 * socket.
 *
 * Opt-in via the "tcp_keepalive" connection option; when it is not set the parent connection
 * logic is used unchanged.
 */
class KeepaliveClient extends Client
{
	private const DEFAULT_KEEPALIVE_IDLE = 60;

	private const DEFAULT_KEEPALIVE_INTERVAL = 30;

	private const DEFAULT_KEEPALIVE_COUNT = 4;


	/**
	 * Tear the client down gracefully without ever crashing the worker at GC / process exit.
	 *
	 * This mirrors Bunny\Client::__destruct() — disconnect() and then pump the event loop with
	 * run() — with two deliberate hardenings. The run() pump is essential and must NOT be removed:
	 * a synchronous Bunny disconnect only sends Channel.Close; the channels are removed and the
	 * final Connection.Close is sent from disconnect()'s promise callback, which only fires once
	 * run() reads the broker's Close-Ok frames. That clean Connection.Close is the barrier that
	 * makes the broker route every already-published message before the socket closes; skipping the
	 * pump tears the TCP connection down abruptly and the broker drops messages still in flight.
	 *
	 * The hardenings cover a broker-initiated close (a channel still open when the broker closes the
	 * connection, e.g. CONNECTION_FORCED on a node drain): there Bunny\Client::disconnect() rejects
	 * with a \LogicException ("All channels have to be closed by now.") that React\Promise stores on
	 * a rejected promise, and the parent destructor resurfaces it FATALLY via done() (and run() then
	 * hits the dead socket). So we (1) consume any rejection with then() instead of done(), and
	 * (2) wrap the whole teardown in a catch-all — a dying client must never take the worker down.
	 */
	public function __destruct()
	{
		try {
			if (! $this->isConnected()) {
				return;
			}

			// disconnect() returns null when there is nothing to wait for; only a promise can reject.
			$promise = $this->disconnect();
			if ($promise instanceof PromiseInterface) {
				// then() (not done()) consumes a rejection so a broker-initiated close cannot
				// resurface as a fatal uncaught error; stop() ends the run() pump once disconnect
				// settles, exactly as the parent destructor does on fulfilment.
				$promise->then(
					function (): void {
						$this->stop();
					},
					function (): void {
						$this->stop();
					}
				);
			}

			// Pump the loop so the Channel.Close/Connection.Close handshake actually completes and
			// every buffered/in-flight publish is delivered before the socket is closed.
			if ($this->isConnected()) {
				$this->run();
			}
		} catch (\Throwable $exception) {
			// Best-effort teardown; on a broken connection disconnect()/run() throw — swallow so a
			// dying client can never crash the worker.
		}
	}


	/**
	 * @return resource
	 */
	protected function getStream()
	{
		// Defer to Bunny when keepalive is not requested, the stream already exists, or an
		// option this override does not reimplement (persistent / async connect) is in play.
		if (
			$this->stream !== null
			|| empty($this->options['tcp_keepalive'])
			|| ! empty($this->options['persistent'])
			|| ! empty($this->options['async_connect'])
			|| ! empty($this->options['async'])
		) {
			return parent::getStream();
		}

		$isSsl = isset($this->options['ssl']) && is_array($this->options['ssl']);

		// Build the full stream context up front. stream_context_create(array) is portable across
		// all supported PHP versions; stream_context_set_options() only exists on PHP 8.3+.
		// TCP_NODELAY is kept here for parity with Bunny's own connect.
		$contextOptions = ['socket' => ['tcp_nodelay' => true]];
		if ($isSsl) {
			$contextOptions['ssl'] = $this->options['ssl'];
		}
		$context = stream_context_create($contextOptions);

		// Always connect over plain TCP first so the socket stays importable and SO_KEEPALIVE
		// can be set before any TLS filter is attached.
		$uri = "tcp://{$this->options['host']}:{$this->options['port']}";

		$stream = @stream_socket_client(
			$uri,
			$errno,
			$errstr,
			(float) $this->options['timeout'],
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ($stream === false) {
			throw new ClientException(
				"Could not connect to {$this->options['host']}:{$this->options['port']}: {$errstr}.",
				(int) $errno
			);
		}

		$this->enableKeepalive($stream);

		// Negotiate TLS now that keepalive is set on the raw socket. The peer name / verification
		// come from the ssl context options, identical to Bunny's "ssl://" connect.
		if ($isSsl && @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
			throw new ClientException(
				"Failed to enable TLS encryption to {$this->options['host']}:{$this->options['port']}."
			);
		}

		if (isset($this->options['read_write_timeout'])) {
			$readWriteTimeout = (float) $this->options['read_write_timeout'];
			if ($readWriteTimeout < 0) {
				$readWriteTimeout = -1;
			}
			$seconds = (int) floor($readWriteTimeout);
			$microseconds = (int) (($readWriteTimeout - $seconds) * 10e6);
			stream_set_timeout($stream, $seconds, $microseconds);
		}

		$this->stream = $stream;

		return $this->stream;
	}


	/**
	 * @param resource $stream
	 */
	private function enableKeepalive($stream): void
	{
		if (! function_exists('socket_import_stream')) {
			return;
		}

		$socket = socket_import_stream($stream);
		if ($socket === false || $socket === null) {
			return;
		}

		@socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);

		$idle = (int) ($this->options['tcp_keepalive_idle'] ?? self::DEFAULT_KEEPALIVE_IDLE);
		$interval = (int) ($this->options['tcp_keepalive_interval'] ?? self::DEFAULT_KEEPALIVE_INTERVAL);
		$count = (int) ($this->options['tcp_keepalive_count'] ?? self::DEFAULT_KEEPALIVE_COUNT);

		// Per-socket timers (Linux). Without them the OS default tcp_keepalive_time (typically
		// 2h) applies, which is far too long to beat an LB idle timeout.
		if (defined('TCP_KEEPIDLE')) {
			@socket_set_option($socket, SOL_TCP, TCP_KEEPIDLE, $idle);
		}
		if (defined('TCP_KEEPINTVL')) {
			@socket_set_option($socket, SOL_TCP, TCP_KEEPINTVL, $interval);
		}
		if (defined('TCP_KEEPCNT')) {
			@socket_set_option($socket, SOL_TCP, TCP_KEEPCNT, $count);
		}

		// Parity with Bunny: TCP_NODELAY on the raw socket too (harmless if already set via context).
		if (defined('TCP_NODELAY')) {
			@socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
		}
	}

}
