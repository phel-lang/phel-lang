<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class PhpArrayPushNode extends Node
{
    private Node $arrayExpr;
    private Node $valueExpr;

    public function __construct(
        NodeEnvironmentInterface $env,
        Node $arrayExpr,
        Node $valueExpr,
        ?SourceLocation $sourceLocation = null
    ) {
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
