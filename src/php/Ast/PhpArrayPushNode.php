<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class PhpArrayPushNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Node
     */
    protected $arrayExpr;

    /**
     * @var Node
     */
    protected $valueExpr;

    public function __construct(NodeEnvironment $env, Node $arrayExpr, Node $valueExpr)
    {
        $this->env = $env;
        $this->arrayExpr = $arrayExpr;
        $this->valueExpr = $valueExpr;
    }

    public function getArrayExpr(): Node {
        return $this->arrayExpr;
    }

    public function getValueExpr(): Node {
        return $this->valueExpr;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}