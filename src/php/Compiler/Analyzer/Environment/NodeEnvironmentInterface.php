<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Environment;

use Phel\Compiler\Analyzer\Ast\RecurFrame;
use Phel\Lang\Symbol;

interface NodeEnvironmentInterface
{
    public const CONTEXT_EXPRESSION = 'expression';
    public const CONTEXT_STATEMENT = 'statement';
    public const CONTEXT_RETURN = 'return';

    /**
     * @return Symbol[]
     */
    public function getLocals(): array;

    public function hasLocal(Symbol $x): bool;

    /**
     * Gets the shadowed name of a local variable.
     *
     * @param Symbol $local The local variable
     */
    public function getShadowed(Symbol $local): ?Symbol;

    public function isShadowed(Symbol $local): bool;

    public function getContext(): string;

    /**
     * @param Symbol[] $locals
     */
    public function withMergedLocals(array $locals): self;

    public function withShadowedLocal(Symbol $local, Symbol $shadow): self;

    /**
     * @param Symbol[] $locals
     */
    public function withoutShadowedLocals(array $locals): self;

    /**
     * @param Symbol[] $locals
     */
    public function withLocals(array $locals): self;

    public function withContext(string $context): self;

    public function withAddedRecurFrame(RecurFrame $frame): self;

    public function withDisallowRecurFrame(): self;

    public function withBoundTo(string $boundTo): self;

    public function withDefAllowed(bool $defAllowed): self;

    public function getCurrentRecurFrame(): ?RecurFrame;

    public function getBoundTo(): string;

    public function isDefAllowed(): bool;
}
