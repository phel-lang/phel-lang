<?php 

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class ApplyNode extends Node {

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
    public function __construct(NodeEnvironment $env, Node $fn, array $arguments, ?SourceLocation $sourceLocation = null)
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