<?php declare(strict_types=1);

namespace Portiny\Doctrine\Tests\Adapter\Nette\Tracy;

use PHPUnit\Framework\TestCase;
use Portiny\Doctrine\Adapter\Nette\Tracy\Helper;

final class HelperTest extends TestCase
{
	public function testDumpSql(): void
	{
		$this->assertSame(
			'<pre class="dump"><strong style="color:blue">SELECT</strong> 1 
<strong style="color:blue">FROM</strong> dual</pre>
',
			Helper::dumpSql('SELECT 1 FROM dual')
		);
	}
}
