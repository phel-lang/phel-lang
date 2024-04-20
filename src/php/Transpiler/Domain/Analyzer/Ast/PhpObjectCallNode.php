<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

final class PhpObjectCallNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly AbstractNode $targetExpr,
        private readonly AbstractNode $callExpr,
        private readonly bool $isStatic,
        private readonly bool $isMethodCall,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
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
        return $this->isStatic;
    }

    public function isMethodCall(): bool
    {
        return $this->isMethodCall;
    }
}
