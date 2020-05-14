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

    /**
     * @param NodeEnvironment $env
     * @param Node[] $stmts
     * @param Node $ret
     */
    public function __construct(NodeEnvironment $env, array $stmts, Node $ret)
    {
        $this->env = $env;
        $this->stmts = $stmts;
        $this->ret = $ret;
    }

    /**
     * @return Node[]
     */
    public function getStmts() {
        return $this->stmts;
    }

    public function getRet(): Node {
        return $this->ret;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}