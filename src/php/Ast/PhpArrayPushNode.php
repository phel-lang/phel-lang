<?php


namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class PhpArrayPushNode extends Node
{

    /**
     * @var Node
     */
    protected $arrayExpr;

    /**
     * @var Node
     */
    protected $valueExpr;

    public function __construct(NodeEnvironment $env, Node $arrayExpr, Node $valueExpr, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->arrayExpr = $arrayExpr;
        $this->valueExpr = $valueExpr;
    }

    public function getArrayExpr(): Node
    {
        return $this->arrayExpr;
    }

    public function getValueExpr(): Node
    {
        return $this->valueExpr;
    }
}
