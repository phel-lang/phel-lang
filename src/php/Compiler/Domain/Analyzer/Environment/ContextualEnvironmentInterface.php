<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

interface ContextualEnvironmentInterface
{
    public function getContext(): string;

    public function isContext(string $context): bool;

    public function withContext(string $context): static;
}
