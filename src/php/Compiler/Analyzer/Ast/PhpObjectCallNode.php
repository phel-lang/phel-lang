<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class PhpObjectCallNode extends AbstractNode
{
    private AbstractNode $targetExpr;
    private AbstractNode $callExpr;
    private bool $static;
    private bool $methodCall;

    public function __construct(
        NodeEnvironmentInterface $env,
        AbstractNode $targetExpr,
        AbstractNode $callExpr,
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

    public function getTargetExpr(): AbstractNode
    {
        return $this->targetExpr;
    }

    public function getCallExpr(): AbstractNode
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
