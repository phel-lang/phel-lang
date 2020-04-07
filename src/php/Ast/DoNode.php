<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class DoNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Node[]
     */
    protected $stmts;

    /**
     * @var Node
     */
    protected $ret;

    public function __construct(NodeEnvironment $env, array $stmts, Node $ret)
    {
        $this->env = $env;
        $this->stmts = $stmts;
        $this->ret = $ret;
    }

    public function getStmts() {
        return $this->stmts;
    }

    public function getRet() {
        return $this->ret;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}