<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class ThrowNode extends Node
{
    private Node $exceptionExpr;

    public function __construct(NodeEnvironmentInterface $env, Node $exceptionExpr, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->exceptionExpr = $exceptionExpr;
    }

    public function getExceptionExpr(): Node
    {
        return $this->exceptionExpr;
    }
}
