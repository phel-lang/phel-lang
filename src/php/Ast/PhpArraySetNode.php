<?php


namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class PhpArraySetNode extends Node
{

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

    public function __construct(NodeEnvironment $env, Node $arrayExpr, Node $accessExpr, Node $valueExpr, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->arrayExpr = $arrayExpr;
        $this->accessExpr = $accessExpr;
        $this->valueExpr = $valueExpr;
    }

    public function getArrayExpr(): Node
    {
        return $this->arrayExpr;
    }

    public function getAccessExpr(): Node
    {
        return $this->accessExpr;
    }

    public function getValueExpr(): Node
    {
        return $this->valueExpr;
    }
}
