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
	 * Tear the client down without ever crashing the worker at GC / process exit.
	 *
	 * Bunny\Client::__destruct() finishes a "clean" shutdown by calling disconnect()->done() and
	 * then re-running the event loop. That is a landmine for a broker-initiated close: when the
	 * broker closes the connection while a consumer channel is still open (CONNECTION_FORCED on a
	 * node drain / rolling broker restart), Bunny\Client::disconnect() rejects with a plain
	 * \LogicException ("All channels have to be closed by now."). React\Promise swallows that into
	 * a rejected promise rather than propagating it synchronously — so the ConsumeCommand reconnect
	 * loop never sees it — and then the parent destructor resurfaces it FATALLY: done() rethrows the
	 * rejection ("All channels have to be closed by now." / "Call to a member function done() on
	 * null"), and the trailing run() hits the dead socket ("Broken pipe or closed connection.").
	 * Those uncaught errors fire outside any try/catch (at GC / exit), which is exactly what flooded
	 * the consumer logs during the nightly maintenance window.
	 *
	 * A dying client must never take the worker down, so we attempt the same graceful disconnect but
	 * consume any rejection here and never re-enter the loop. On the happy path Bunny's synchronous
	 * disconnect closes the channels and the rejection handler is simply never invoked.
	 */
	public function __destruct()
	{
		if (! $this->isConnected()) {
			return;
		}

		try {
			$promise = $this->disconnect();

			// disconnect() returns null when it has nothing to wait for; only a promise can reject.
			if ($promise instanceof PromiseInterface) {
				$promise->then(null, static function (): void {
					// Connection is already gone — swallow so the rejection cannot resurface fatally.
				});
			}
		} catch (\Throwable $exception) {
			// Best-effort teardown; the connection is already broken, nothing left to close.
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
