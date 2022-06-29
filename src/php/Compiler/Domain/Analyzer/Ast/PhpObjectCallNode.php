<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class PhpObjectCallNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private AbstractNode $targetExpr,
        private AbstractNode $callExpr,
        private bool $isStatic,
        private bool $isMethodCall,
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
