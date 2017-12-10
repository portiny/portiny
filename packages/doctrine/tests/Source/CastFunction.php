<?php

namespace Portiny\Doctrine\Tests\Source;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class CastFunction extends FunctionNode
{
    /**
     * {@inheritdoc}
     */
    public function getSql(SqlWalker $sqlWalker)
    {
        /** @var Node $value */
        $value = $this->parameters[DqlFunction::PARAMETER_KEY];
        $type = $this->parameters[DqlFunction::TYPE_KEY];

        $type = strtolower($type);
        if ($type === 'datetime') {
            $timestampFunction = new Timestamp(
                array(SimpleFunction::PARAMETER_KEY => $value)
            );

            return $timestampFunction->getSql($sqlWalker);
        }

        if ($type === 'json' && ! $sqlWalker->getConnection()->getDatabasePlatform()->hasNativeJsonType()) {
            $type = 'text';
        }

        if ($type === 'bool') {
            $type = 'boolean';
        }

        if ($type === 'string') {
            $type = 'varchar';
        }

        return 'CAST(' . $this->getExpressionValue($value, $sqlWalker) . ' AS ' . $type . ')';
    }


	public function parse(Parser $parser): void
	{

	}
}
