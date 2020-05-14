<?php 

namespace Phel\Ast;

use Phel\NodeEnvironment;

class PhpArrayUnsetNode implements Node {

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

    public function __construct(NodeEnvironment $env, Node $arrayExpr, Node $accessExpr)
    {
        $this->env = $env;
        $this->arrayExpr = $arrayExpr;
        $this->accessExpr = $accessExpr;
    }

    public function getArrayExpr(): Node {
        return $this->arrayExpr;
    }

    public function getAccessExpr(): Node {
        return $this->accessExpr;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}