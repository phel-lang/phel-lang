<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class PhpObjectCallNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Node
     */
    protected $targetExpr;

    /**
     * @var Node
     */
    protected $callExpr;

    /**
     * @var boolean
     */
    protected $static;

    /**
     * @var boolean
     */
    protected $methodCall;

    public function __construct(NodeEnvironment $env, Node $targetExpr, Node $callExpr, bool $isStatic, bool $isMethodCall)
    {
        $this->env = $env;
        $this->targetExpr = $targetExpr;
        $this->callExpr = $callExpr;
        $this->static = $isStatic;
        $this->methodCall = $isMethodCall;
    }

    public function getTargetExpr(): Node {
        return $this->targetExpr;
    }

    public function getCallExpr(): Node {
        return $this->callExpr;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }

    public function isStatic(): bool {
        return $this->static;
    }
    
    public function isMethodCall(): bool {
        return $this->methodCall;
    }
}