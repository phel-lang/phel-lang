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
    public function withMergedLocals(array $locals): NodeEnvironmentInterface;

    public function withShadowedLocal(Symbol $local, Symbol $shadow): NodeEnvironmentInterface;

    /**
     * @param Symbol[] $locals
     */
    public function withLocals(array $locals): NodeEnvironmentInterface;

    public function withContext(string $context): NodeEnvironmentInterface;

    public function withAddedRecurFrame(RecurFrame $frame): NodeEnvironmentInterface;

    public function withDisallowRecurFrame(): NodeEnvironmentInterface;

    public function withBoundTo(string $boundTo): NodeEnvironmentInterface;

    public function withDefAllowed(bool $defAllowed): NodeEnvironmentInterface;

    public function getCurrentRecurFrame(): ?RecurFrame;

    public function getBoundTo(): string;

    public function isDefAllowed(): bool;
}
