<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Compiler\NodeEnvironment;

final class ForeachNode extends Node
{
    private Node $bodyExpr;
    private Node $listExpr;
    private Symbol $valueSymbol;
    private ?Symbol $keySymbol;

    public function __construct(
        NodeEnvironment $env,
        Node $bodyExpr,
        Node $listExpr,
        Symbol $valueSymbol,
        ?Symbol $keySymbol = null,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->bodyExpr = $bodyExpr;
        $this->listExpr = $listExpr;
        $this->valueSymbol = $valueSymbol;
        $this->keySymbol = $keySymbol;
    }

    public function getBodyExpr(): Node
    {
        return $this->bodyExpr;
    }

    public function getListExpr(): Node
    {
        return $this->listExpr;
    }

    public function getValueSymbol(): Symbol
    {
        return $this->valueSymbol;
    }

    public function getKeySymbol(): ?Symbol
    {
        return $this->keySymbol;
    }
}
