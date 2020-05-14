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

    /**
     * @param NodeEnvironment $env
     * @param BindingNode[] $bindings
     * @param Node $node
     * @param bool $isLoop
     */
    public function __construct(NodeEnvironment $env, array $bindings, Node $bodyExpr, bool $isLoop)
    {
        $this->env = $env;
        $this->bindings = $bindings;
        $this->bodyExpr = $bodyExpr;
        $this->isLoop = $isLoop;
    }

    /**
     * @return BindingNode[]
     */
    public function getBindings() {
        return $this->bindings;
    }

    public function getBodyExpr(): Node {
        return $this->bodyExpr;
    }

    public function isLoop(): bool {
        return $this->isLoop;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}