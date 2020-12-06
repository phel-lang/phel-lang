<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class PhpObjectCallNode extends Node
{
    private Node $targetExpr;
    private Node $callExpr;
    private bool $static;
    private bool $methodCall;

    public function __construct(
        NodeEnvironmentInterface $env,
        Node $targetExpr,
        Node $callExpr,
        bool $isStatic,
        bool $isMethodCall,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->targetExpr = $targetExpr;
        $this->callExpr = $callExpr;
        $this->static = $isStatic;
        $this->methodCall = $isMethodCall;
    }

    public function getTargetExpr(): Node
    {
        return $this->targetExpr;
    }

    public function getCallExpr(): Node
    {
        return $this->callExpr;
    }

    public function isStatic(): bool
    {
        return $this->static;
    }

    public function isMethodCall(): bool
    {
        return $this->methodCall;
    }
}
