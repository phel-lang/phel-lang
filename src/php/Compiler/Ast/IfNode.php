<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\NodeEnvironment;
use Phel\Lang\SourceLocation;

final class IfNode extends Node
{
    private Node $testExpr;
    private Node $thenExpr;
    private Node $elseExpr;

    public function __construct(
        NodeEnvironment $env,
        Node $testExpr,
        Node $thenExpr,
        Node $elseExpr,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->testExpr = $testExpr;
        $this->thenExpr = $thenExpr;
        $this->elseExpr = $elseExpr;
    }

    public function getTestExpr(): Node
    {
        return $this->testExpr;
    }

    public function getThenExpr(): Node
    {
        return $this->thenExpr;
    }

    public function getElseExpr(): Node
    {
        return $this->elseExpr;
    }
}
