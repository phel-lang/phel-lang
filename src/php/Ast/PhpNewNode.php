<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class PhpNewNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Node
     */
    protected $classExpr;

    /**
     * @var Node[]
     */
    protected $args;

    /**
     * @param NodeEnvironment $env
     * @param Node $classExpr
     * @param Node[] $args
     */
    public function __construct(NodeEnvironment $env, Node $classExpr, array $args)
    {
        $this->env = $env;
        $this->classExpr = $classExpr;
        $this->args = $args;
    }

    public function getClassExpr(): Node {
        return $this->classExpr;
    }

    /**
     * @return Node[]
     */
    public function getArgs() {
        return $this->args;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }

    
}