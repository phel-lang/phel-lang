<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class CallNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Node
     */
    protected $fn;

    /**
     * @var Node[]
     */
    protected $arguments;

    public function __construct(NodeEnvironment $env, Node $fn, $arguments)
    {
        $this->env = $env;
        $this->fn = $fn;
        $this->arguments = $arguments;
    }

    public function getFn() {
        return $this->fn;
    }

    public function getArguments() {
        return $this->arguments;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}