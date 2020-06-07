<?php


namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class ThrowNode extends Node
{

    /**
     * @var Node
     */
    protected $exceptionExpr;

    public function __construct(NodeEnvironment $env, Node $exceptionExpr, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->exceptionExpr = $exceptionExpr;
    }

    public function getExceptionExpr(): Node
    {
        return $this->exceptionExpr;
    }
}
