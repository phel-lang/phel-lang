<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Lang\Symbol;

use function array_key_exists;
use function count;

final class NodeEnvironment implements NodeEnvironmentInterface
{
    public const string CONTEXT_EXPRESSION = 'expression';

    public const string CONTEXT_STATEMENT = 'statement';

    public const string CONTEXT_RETURN = 'return';

    /** Use Registry::getDefinitionReference() instead of Registry::getDefinition() */
    private bool $globalReference = false;

    /**
     * Set by `DefSymbol` on a `def`-owned single-arity fn so
     * `FnSymbol::analyzeSingle` skips its inline return-type inference walk;
     * `DefSymbol` runs that walk once after grafting param tags.
     */
    private bool $returnInferenceDeferred = false;

    /**
     * Derived index of $locals keyed by name (first occurrence wins), kept in
     * sync with $locals so lookups are O(1) instead of a linear scan.
     *
     * @var array<string, Symbol>
     */
    private array $localsByName;

    /**
     * Derived reverse index of $shadowed: shadow name => original local name,
     * so findLocalByShadowedName() is O(1) instead of an O(m*n) double loop.
     *
     * @var array<string, string>
     */
    private array $shadowedReverse;

    /**
     * @param array<int, Symbol>     $locals      A list of local symbols
     * @param string                 $context     The current context (Expression, Statement or Return)
     * @param array<string, Symbol>  $shadowed    A mapping list of local variables to shadowed names
     * @param array<RecurFrame|null> $recurFrames A list of RecurFrame
     * @param string                 $boundTo     A variable this is bound to
     */
    public function __construct(
        private array $locals,
        private string $context,
        private array $shadowed,
        private array $recurFrames,
        private string $boundTo = '',
    ) {
        $this->localsByName = $this->indexLocalsByName($locals);
        $this->shadowedReverse = $this->indexShadowedReverse($shadowed);
    }

    public static function empty(): NodeEnvironmentInterface
    {
        return new self([], self::CONTEXT_STATEMENT, [], []);
    }

    /**
     * @return array<int, Symbol>
     */
    public function getLocals(): array
    {
        return $this->locals;
    }

    public function hasLocal(Symbol $x): bool
    {
        return isset($this->localsByName[$x->getName()]);
    }

    /**
     * Gets the shadowed name of a local variable.
     *
     * @param Symbol $local The local variable
     */
    public function getShadowed(Symbol $local): ?Symbol
    {
        if ($this->isShadowed($local)) {
            return $this->shadowed[$local->getName()];
        }

        return null;
    }

    public function isShadowed(Symbol $local): bool
    {
        return array_key_exists($local->getName(), $this->shadowed);
    }

    public function findLocalByShadowedName(string $shadowedName): ?Symbol
    {
        $originalName = $this->shadowedReverse[$shadowedName] ?? null;
        if ($originalName === null) {
            return null;
        }

        return $this->localsByName[$originalName] ?? null;
    }

    public function isContext(string $context): bool
    {
        return $this->context === $context;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function withMergedLocals(array $locals): NodeEnvironmentInterface
    {
        $seen = [];
        $allLocalSymbols = [];

        foreach ($this->locals as $local) {
            $name = $local->getName();
            if (!isset($seen[$name])) {
                $seen[$name] = true;
                $allLocalSymbols[] = $local;
            }
        }

        foreach ($locals as $local) {
            $name = $local->getName();
            if (!isset($seen[$name])) {
                $seen[$name] = true;
                $allLocalSymbols[] = $local;
            }
        }

        return $this
            ->withLocals($allLocalSymbols)
            ->withoutShadowedLocals($locals);
    }

    public function withShadowedLocal(Symbol $local, Symbol $shadow): NodeEnvironmentInterface
    {
        $result = clone $this;
        $result->shadowed = array_merge($this->shadowed, [$local->getName() => $shadow]);
        $result->shadowedReverse = $this->indexShadowedReverse($result->shadowed);

        return $result;
    }

    public function withLocalAndShadow(Symbol $local, Symbol $shadow): NodeEnvironmentInterface
    {
        return $this->withLocalsAndShadows([[$local, $shadow]]);
    }

    /**
     * @param list<array{Symbol, Symbol}> $pairs
     */
    public function withLocalsAndShadows(array $pairs): NodeEnvironmentInterface
    {
        $result = clone $this;

        foreach ($pairs as [$local, $shadow]) {
            $localName = $local->getName();
            $shadowName = $shadow->getName();

            // locals + localsByName: first occurrence of a name wins.
            if (!isset($result->localsByName[$localName])) {
                $result->locals[] = $local;
                $result->localsByName[$localName] = $local;
            }

            // shadowed forward map: last write wins. A rebind of the same
            // name first drops the prior entry (freeing its insertion slot)
            // and re-appends at the end — matching the chained
            // `withoutShadowedLocals()->withShadowedLocal()` path — then the
            // reverse index is recomputed because the freed slot can change
            // which local wins a colliding shadow name. The common case (a
            // fresh name with a fresh shadow) stays O(1).
            if (array_key_exists($localName, $result->shadowed)) {
                unset($result->shadowed[$localName]);
                $result->shadowed[$localName] = $shadow;
                $result->shadowedReverse = $this->indexShadowedReverse($result->shadowed);
            } else {
                $result->shadowed[$localName] = $shadow;

                // shadowedReverse: first occurrence of a shadow name wins.
                if (!isset($result->shadowedReverse[$shadowName])) {
                    $result->shadowedReverse[$shadowName] = $localName;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<int, Symbol> $locals
     */
    public function withoutShadowedLocals(array $locals): self
    {
        $result = clone $this;
        foreach ($locals as $local) {
            unset($result->shadowed[$local->getName()]);
        }

        $result->shadowedReverse = $this->indexShadowedReverse($result->shadowed);

        return $result;
    }

    public function withLocals(array $locals): NodeEnvironmentInterface
    {
        $result = clone $this;
        $result->locals = $locals;
        $result->localsByName = $this->indexLocalsByName($locals);

        return $result;
    }

    public function withReturnContext(): self
    {
        return $this->withContext(self::CONTEXT_RETURN);
    }

    public function withStatementContext(): self
    {
        return $this->withContext(self::CONTEXT_STATEMENT);
    }

    public function withExpressionContext(): self
    {
        return $this->withContext(self::CONTEXT_EXPRESSION);
    }

    public function withEnvContext(ContextualEnvironmentInterface $env): self
    {
        return $this->withContext($env->getContext());
    }

    public function withContext(string $context): static
    {
        if ($this->context === $context) {
            return $this;
        }

        $result = clone $this;
        $result->context = $context;

        return $result;
    }

    public function withUseGlobalReference(bool $useGlobalReference): NodeEnvironmentInterface
    {
        if ($this->globalReference === $useGlobalReference) {
            return $this;
        }

        $result = clone $this;
        $result->globalReference = $useGlobalReference;

        return $result;
    }

    public function withAddedRecurFrame(RecurFrame $frame): NodeEnvironmentInterface
    {
        $result = clone $this;
        $result->recurFrames = [...$this->recurFrames, $frame];

        return $result;
    }

    public function withDisallowRecurFrame(): NodeEnvironmentInterface
    {
        $result = clone $this;
        $result->recurFrames = [...$this->recurFrames, null];

        return $result;
    }

    public function withBoundTo(string $boundTo): NodeEnvironmentInterface
    {
        if ($this->boundTo === $boundTo) {
            return $this;
        }

        $result = clone $this;
        $result->boundTo = $boundTo;

        return $result;
    }

    public function withReturnInferenceDeferred(bool $deferred): NodeEnvironmentInterface
    {
        if ($this->returnInferenceDeferred === $deferred) {
            return $this;
        }

        $result = clone $this;
        $result->returnInferenceDeferred = $deferred;

        return $result;
    }

    public function isReturnInferenceDeferred(): bool
    {
        return $this->returnInferenceDeferred;
    }

    public function getCurrentRecurFrame(): ?RecurFrame
    {
        if ($this->recurFrames === []) {
            return null;
        }

        return $this->recurFrames[count($this->recurFrames) - 1];
    }

    public function getBoundTo(): string
    {
        return $this->boundTo;
    }

    public function useGlobalReference(): bool
    {
        return $this->globalReference;
    }

    /**
     * @param array<int, Symbol> $locals
     *
     * @return array<string, Symbol>
     */
    private function indexLocalsByName(array $locals): array
    {
        $index = [];
        foreach ($locals as $local) {
            $name = $local->getName();
            if (!isset($index[$name])) {
                $index[$name] = $local;
            }
        }

        return $index;
    }

    /**
     * @param array<string, Symbol> $shadowed
     *
     * @return array<string, string>
     */
    private function indexShadowedReverse(array $shadowed): array
    {
        $reverse = [];
        foreach ($shadowed as $originalName => $shadowSymbol) {
            $shadowName = $shadowSymbol->getName();
            if (!isset($reverse[$shadowName])) {
                $reverse[$shadowName] = $originalName;
            }
        }

        return $reverse;
    }
}
