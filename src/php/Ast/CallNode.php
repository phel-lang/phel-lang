<?php 

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class CallNode extends Node {

    /**
     * @var Node
     */
    protected $fn;

    /**
     * @var Node[]
     */
    protected $arguments;

    /**
     * @param NodeEnvironment $env
     * @param Node $fn
     * @param Node[] $arguments
     */
    public function __construct(NodeEnvironment $env, Node $fn, $arguments, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
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
}