<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class BindingNode extends AbstractNode
{
    private Symbol $symbol;
    private Symbol $shadow;
    private AbstractNode $initExpr;

    public function __construct(
        NodeEnvironmentInterface $env,
        Symbol $symbol,
        Symbol $shadow,
        AbstractNode $initExpr,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->symbol = $symbol;
        $this->shadow = $shadow;
        $this->initExpr = $initExpr;
    }

    public function getSymbol(): Symbol
    {
        return $this->symbol;
    }

    public function getInitExpr(): AbstractNode
    {
        return $this->initExpr;
    }

    public function getShadow(): Symbol
    {
        return $this->shadow;
    }
}
