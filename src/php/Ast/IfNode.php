<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class IfNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

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

    public function __construct(NodeEnvironment $env, Node $testExpr, Node $thenExpr, Node $elseExpr)
    {
        $this->env = $env;
        $this->testExpr = $testExpr;
        $this->thenExpr = $thenExpr;
        $this->elseExpr = $elseExpr;
    }

    public function getTestExpr(): Node {
        return $this->testExpr;
    }

    public function getThenExpr(): Node {
        return $this->thenExpr;
    }

    public function getElseExpr(): Node {
        return $this->elseExpr;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}