<?php 

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

class PhpArrayUnsetNode extends Node {

    /**
     * @var Node
     */
    protected $arrayExpr;

    /**
     * @var Node
     */
    protected $accessExpr;

    public function __construct(NodeEnvironment $env, Node $arrayExpr, Node $accessExpr, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->arrayExpr = $arrayExpr;
        $this->accessExpr = $accessExpr;
    }

    public function getArrayExpr(): Node {
        return $this->arrayExpr;
    }

    public function getAccessExpr(): Node {
        return $this->accessExpr;
    }
}