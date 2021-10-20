<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class SetVarNode extends AbstractNode
{
    private AbstractNode $symbol;
    private AbstractNode $valueExpr;

    public function __construct(
        NodeEnvironmentInterface $env,
        AbstractNode $symbol,
        AbstractNode $valueExpr,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->symbol = $symbol;
        $this->valueExpr = $valueExpr;
    }

    public function getSymbol(): AbstractNode
    {
        return $this->symbol;
    }

    public function getValueExpr(): AbstractNode
    {
        return $this->valueExpr;
    }
}
