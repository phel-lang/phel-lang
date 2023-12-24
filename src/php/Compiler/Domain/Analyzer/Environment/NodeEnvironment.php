<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Lang\Symbol;

use function array_key_exists;
use function count;
use function in_array;

final class NodeEnvironment implements NodeEnvironmentInterface
{
    public const CONTEXT_EXPRESSION = 'expression';

    public const CONTEXT_STATEMENT = 'statement';

    public const CONTEXT_RETURN = 'return';

    /** Def inside of def should not work. This flag help us to keep track of this. */
    private bool $defAllowed = true;

    /** Use Registery::getDefinitionReference() instead of Registry::getDefinition() */
    private bool $globalReference = false;

    /**
     * @param array<int, Symbol> $locals A list of local symbols
     * @param string $context The current context (Expression, Statement or Return)
     * @param array<string, Symbol> $shadowed A mapping list of local variables to shadowed names
     * @param array<RecurFrame|null> $recurFrames A list of RecurFrame
     * @param string $boundTo A variable this is bound to
     */
    public function __construct(
        private array $locals,
        private string $context,
        private array $shadowed,
        private array $recurFrames,
        private string $boundTo = '',
    ) {
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
        return in_array(Symbol::create($x->getName()), $this->locals, false);
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
        $allLocalSymbols = [...$this->locals, ...array_map(
            static fn (Symbol $s): Symbol => Symbol::create($s->getName()),
            $locals,
        )];

        return $this
            ->withLocals(array_unique($allLocalSymbols))
            ->withoutShadowedLocals($locals);
    }

    public function withShadowedLocal(Symbol $local, Symbol $shadow): NodeEnvironmentInterface
    {
        $result = clone $this;
        $result->shadowed = array_merge($this->shadowed, [$local->getName() => $shadow]);

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

        return $result;
    }

    public function withLocals(array $locals): NodeEnvironmentInterface
    {
        $result = clone $this;
        $result->locals = $locals;

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
        $result = clone $this;
        $result->context = $context;

        return $result;
    }

    public function withUseGlobalReference(bool $useGlobalReference): NodeEnvironmentInterface
    {
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
        $result = clone $this;
        $result->boundTo = $boundTo;

        return $result;
    }

    public function withDefAllowed(bool $defAllowed): NodeEnvironmentInterface
    {
        $result = clone $this;
        $result->defAllowed = $defAllowed;

        return $result;
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

    public function isDefAllowed(): bool
    {
        return $this->defAllowed;
    }

    public function useGlobalReference(): bool
    {
        return $this->globalReference;
    }
}
