<?php 

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class TupleNode extends Node {

    /**
     * @var Node[]
     */
    protected $args;

    /**
     * @param NodeEnvironment $env
     * @param Node[] $args
     */
    public function __construct(NodeEnvironment $env, array $args, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->args = $args;
    }

    /**
     * @return Node[]
     */
    public function getArgs() {
        return $this->args;
    }
}