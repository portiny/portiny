<?php declare(strict_types = 1);

namespace Portiny\Doctrine\Tests\Source;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class CastFunction extends FunctionNode
{

	/**
	 * {@inheritdoc}
	 */
	public function getSql(SqlWalker $sqlWalker)
	{
		return 'CAST()';
	}


	public function parse(Parser $parser): void
	{
	}

}
