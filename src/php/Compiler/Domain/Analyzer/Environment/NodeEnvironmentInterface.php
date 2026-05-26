<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Lang\Symbol;

interface NodeEnvironmentInterface extends ContextualEnvironmentInterface
{
    /**
     * @return array<int, Symbol>
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

    /**
     * Reverse-lookup helper: given the **shadowed** name of a binding
     * (e.g. `a_3` for `(let [a 0] ...)`), return the original user-facing
     * local `Symbol` (with its `:tag` meta intact) when one exists in
     * scope; otherwise `null`.
     *
     * Lets a reference whose AST stores the shadowed identifier (as
     * produced by {@see \Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzeSymbol})
     * still recover the binding's analyser metadata - in particular the
     * `^int` / `^float` tag the call-site specialisers consult.
     */
    public function findLocalByShadowedName(string $shadowedName): ?Symbol;

    /**
     * @param array<int, Symbol> $locals
     */
    public function withMergedLocals(array $locals): self;

    public function withShadowedLocal(Symbol $local, Symbol $shadow): self;

    /**
     * @param array<int, Symbol> $locals
     */
    public function withoutShadowedLocals(array $locals): self;

    /**
     * @param array<int, Symbol> $locals
     */
    public function withLocals(array $locals): self;

    public function withUseGlobalReference(bool $useGlobalReference): self;

    public function withAddedRecurFrame(RecurFrame $frame): self;

    public function withDisallowRecurFrame(): self;

    public function withBoundTo(string $boundTo): self;

    public function getCurrentRecurFrame(): ?RecurFrame;

    public function getBoundTo(): string;

    public function useGlobalReference(): bool;

    public function withReturnContext(): self;

    public function withStatementContext(): self;

    public function withExpressionContext(): self;

    public function withEnvContext(ContextualEnvironmentInterface $env): self;
}
