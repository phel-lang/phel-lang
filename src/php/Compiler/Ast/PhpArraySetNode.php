<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Lang\SourceLocation;
use Phel\Compiler\NodeEnvironment;

final class PhpArraySetNode extends Node
{
    private Node $arrayExpr;
    private Node $accessExpr;
    private Node $valueExpr;

    public function __construct(
        NodeEnvironment $env,
        Node $arrayExpr,
        Node $accessExpr,
        Node $valueExpr,
        ?SourceLocation $sourceLocation = null
    ) {
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
