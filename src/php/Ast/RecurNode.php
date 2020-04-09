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

    public function __construct(NodeEnvironment $env, RecurFrame $frame, array $exprs)
    {
        $this->env = $env;
        $this->frame = $frame;
        $this->exprs = $exprs;
    }

    public function getFrame() {
        return $this->frame;
    }

    public function getExprs() {
        return $this->exprs;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}