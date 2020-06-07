<?php


namespace Phel\Ast;

use Phel\Lang\Symbol;
use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class BindingNode extends Node
{

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

    public function __construct(NodeEnvironment $env, Symbol $symbol, Symbol $shadow, Node $initExpr, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->symbol = $symbol;
        $this->shadow = $shadow;
        $this->initExpr = $initExpr;
    }

    public function getSymbol(): Symbol
    {
        return $this->symbol;
    }

    public function getInitExpr(): Node
    {
        return $this->initExpr;
    }

    public function getShadow(): Symbol
    {
        return $this->shadow;
    }
}
