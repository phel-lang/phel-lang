<?php 

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;
use Phel\RecurFrame;

class RecurNode extends Node {

    /**
     * @var RecurFrame
     */
    protected $frame;

    /**
     * @var Node[]
     */
    protected $exprs;

    /**
     * @param NodeEnvironment $env
     * @param RecurFrame $frame
     * @param Node[] $exprs
     */
    public function __construct(NodeEnvironment $env, RecurFrame $frame, array $exprs, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->frame = $frame;
        $this->exprs = $exprs;
    }

    public function getFrame(): RecurFrame {
        return $this->frame;
    }

    /**
     * @return Node[]
     */
    public function getExprs() {
        return $this->exprs;
    }
}