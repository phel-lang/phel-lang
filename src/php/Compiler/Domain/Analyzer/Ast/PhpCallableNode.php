<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

/**
 * First-class callable form `(php/callable ...)`, emitting native PHP 8.1
 * `(...)` syntax. Three target shapes:
 *
 * - free function: `targetExpr === null`, `name` is the function reference;
 * - static method: `targetExpr` is a `PhpClassNameNode`, `isStatic === true`;
 * - instance method: `targetExpr` is the analyzed object expression.
 */
final class PhpCallableNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly ?AbstractNode $targetExpr,
        private readonly string $name,
        private readonly bool $isStatic,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getTargetExpr(): ?AbstractNode
    {
        return $this->targetExpr;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isStatic(): bool
    {
        return $this->isStatic;
    }
}
