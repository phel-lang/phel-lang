<?php 

namespace Phel\Ast;

use Phel\Lang\Symbol;
use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class MethodCallNode extends Node {

    /**
     * @var Symbol
     */
    protected $fn;

    /**
     * @var Node[]
     */
    protected $args;

    /**
     * @param NodeEnvironment $env
     * @param Symbol $fn
     * @param Node[] $args
     */
    public function __construct(NodeEnvironment $env, Symbol $fn, array $args, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->fn = $fn;
        $this->args = $args;
    }

    public function getFn(): Symbol {
        return $this->fn;
    }

    /**
     * @return Node[]
     */
    public function getArgs() {
        return $this->args;
    }
}