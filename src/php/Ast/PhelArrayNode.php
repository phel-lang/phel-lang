<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class PhelArrayNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Node[]
     */
    protected $args;

    public function __construct(NodeEnvironment $env, array $args)
    {
        $this->env = $env;
        $this->args = $args;
    }

    public function getArgs() {
        return $this->args;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}