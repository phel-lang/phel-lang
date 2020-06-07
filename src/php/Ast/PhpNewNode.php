<?php


namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class PhpNewNode extends Node
{

    /**
     * @var Node
     */
    protected $classExpr;

    /**
     * @var Node[]
     */
    protected $args;

    /**
     * @param NodeEnvironment $env
     * @param Node $classExpr
     * @param Node[] $args
     */
    public function __construct(NodeEnvironment $env, Node $classExpr, array $args, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->classExpr = $classExpr;
        $this->args = $args;
    }

    public function getClassExpr(): Node
    {
        return $this->classExpr;
    }

    /**
     * @return Node[]
     */
    public function getArgs()
    {
        return $this->args;
    }
}
