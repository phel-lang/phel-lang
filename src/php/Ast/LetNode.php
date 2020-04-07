<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class LetNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var BindingNode[]
     */
    protected $bindings;

    /**
     * @var Node
     */
    protected $bodyExpr;

    /**
     * @var bool
     */
    protected $isLoop;

    public function __construct(NodeEnvironment $env, array $bindings, Node $bodyExpr, bool $isLoop)
    {
        $this->env = $env;
        $this->bindings = $bindings;
        $this->bodyExpr = $bodyExpr;
        $this->isLoop = $isLoop;
    }

    public function getBindings() {
        return $this->bindings;
    }

    public function getBodyExpr() {
        return $this->bodyExpr;
    }

    public function isLoop() {
        return $this->isLoop;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}