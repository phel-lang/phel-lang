<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class PhpArraySetNode implements Node {

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
    protected $accessExpr;

    /**
     * @var Node
     */
    protected $valueExpr;

    public function __construct(NodeEnvironment $env, Node $arrayExpr, Node $accessExpr, Node $valueExpr)
    {
        $this->env = $env;
        $this->arrayExpr = $arrayExpr;
        $this->accessExpr = $accessExpr;
        $this->valueExpr = $valueExpr;
    }

    public function getArrayExpr(): Node {
        return $this->arrayExpr;
    }

    public function getAccessExpr(): Node {
        return $this->accessExpr;
    }

    public function getValueExpr(): Node {
        return $this->valueExpr;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}