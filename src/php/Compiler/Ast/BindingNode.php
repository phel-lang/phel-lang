<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class BindingNode extends Node
{
    private Symbol $symbol;
    private Symbol $shadow;
    private Node $initExpr;

    public function __construct(
        NodeEnvironmentInterface $env,
        Symbol $symbol,
        Symbol $shadow,
        Node $initExpr,
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

    public function getInitExpr(): Node
    {
        return $this->initExpr;
    }

    public function getShadow(): Symbol
    {
        return $this->shadow;
    }
}
