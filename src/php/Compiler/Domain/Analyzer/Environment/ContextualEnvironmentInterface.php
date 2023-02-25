<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

interface ContextualEnvironmentInterface
{
    public function getContext(): string;

    public function withReturnContext(): static;

    public function withStatementContext(): static;

    public function withExpressionContext(): static;

    public function withContext(string $context): static;
}
