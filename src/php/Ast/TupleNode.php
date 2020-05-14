<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class TupleNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Node[]
     */
    protected $args;

    /**
     * @param NodeEnvironment $env
     * @param Node[] $args
     */
    public function __construct(NodeEnvironment $env, array $args)
    {
        $this->env = $env;
        $this->args = $args;
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