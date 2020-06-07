<?php


namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class IfNode extends Node
{

    /**
     * @var Node
     */
    protected $testExpr;

    /**
     * @var Node
     */
    protected $thenExpr;

    /**
     * @var Node
     */
    protected $elseExpr;

    public function __construct(NodeEnvironment $env, Node $testExpr, Node $thenExpr, Node $elseExpr, ?SourceLocation $sourceLocation = null)
    {
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
