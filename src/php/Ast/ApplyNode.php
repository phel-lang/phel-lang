<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class ApplyNode implements Node {

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

    /**
     * Construtor
     * 
     * @param NodeEnvironment $env
     * @param Node $fn
     * @param Node[] $arguments
     */
    public function __construct(NodeEnvironment $env, Node $fn, array $arguments)
    {
        $this->env = $env;
        $this->fn = $fn;
        $this->arguments = $arguments;
    }

    public function getFn(): Node {
        return $this->fn;
    }

    /**
     * @return Node[]
     */
    public function getArguments() {
        return $this->arguments;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}