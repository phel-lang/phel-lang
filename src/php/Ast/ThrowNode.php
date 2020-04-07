<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class ThrowNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Node
     */
    protected $exceptionExpr;

    public function __construct(NodeEnvironment $env, Node $exceptionExpr)
    {
        $this->env = $env;
        $this->exceptionExpr = $exceptionExpr;
    }

    public function getExceptionExpr() {
        return $this->exceptionExpr;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}