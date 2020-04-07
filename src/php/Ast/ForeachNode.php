<?php 

namespace Phel\Ast;

use Phel\Lang\Symbol;
use Phel\NodeEnvironment;

class ForeachNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Node
     */
    protected $bodyExpr;

    /**
     * @var Node
     */
    protected $listExpr;

    /**
     * @var Symbol
     */
    protected $valueSymbol;

    /**
     * @var Symbol|null
     */
    protected $keySymbol;

    public function __construct(NodeEnvironment $env, Node $bodyExpr, Node $listExpr, Symbol $valueSymbol, $keySymbol = null)
    {
        $this->env = $env;
        $this->bodyExpr = $bodyExpr;
        $this->listExpr = $listExpr;
        $this->valueSymbol = $valueSymbol;
        $this->keySymbol = $keySymbol;
    }

    public function getBodyExpr() {
        return $this->bodyExpr;
    }

    public function getListExpr() {
        return $this->listExpr;
    }

    public function getValueSymbol() {
        return $this->valueSymbol;
    }

    public function getKeySymbol() {
        return $this->keySymbol;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}