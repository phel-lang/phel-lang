<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;
use Phel\RecurFrame;

class RecurNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

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
    public function __construct(NodeEnvironment $env, RecurFrame $frame, array $exprs)
    {
        $this->env = $env;
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

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}