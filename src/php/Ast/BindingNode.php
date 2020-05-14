<?php 

namespace Phel\Ast;

use Phel\Lang\Symbol;
use Phel\NodeEnvironment;

class BindingNode implements Node {

    /**
     * @var NodeEnvironment
     */
    protected $env;

    /**
     * @var Symbol
     */
    protected $symbol;

    /**
     * @var Symbol
     */
    protected $shadow;

    /**
     * @var Node
     */
    protected $initExpr;

    public function __construct(NodeEnvironment $env, Symbol $symbol, Symbol $shadow, Node $initExpr)
    {
        $this->env = $env;
        $this->symbol = $symbol;
        $this->shadow = $shadow;
        $this->initExpr = $initExpr;
    }

    public function getSymbol(): Symbol {
        return $this->symbol;
    }

    public function getInitExpr(): Node {
        return $this->initExpr;
    }

    public function getShadow(): Symbol {
        return $this->shadow;
    }

    public function getEnv(): NodeEnvironment {
        return $this->env;
    }
}