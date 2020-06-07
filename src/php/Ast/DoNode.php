<?php


namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class DoNode extends Node
{

    /**
     * @var Node[]
     */
    protected $stmts;

    /**
     * @var Node
     */
    protected $ret;

    /**
     * @param NodeEnvironment $env
     * @param Node[] $stmts
     * @param Node $ret
     */
    public function __construct(NodeEnvironment $env, array $stmts, Node $ret, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->stmts = $stmts;
        $this->ret = $ret;
    }

    /**
     * @return Node[]
     */
    public function getStmts()
    {
        return $this->stmts;
    }

    public function getRet(): Node
    {
        return $this->ret;
    }
}
