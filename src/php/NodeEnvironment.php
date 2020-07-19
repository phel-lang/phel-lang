<?php

declare(strict_types=1);

namespace Phel;

use Phel\Lang\Symbol;

final class NodeEnvironment
{
    public const CTX_EXPR = 'expr';
    public const CTX_STMT = 'stmt';
    public const CTX_RET = 'ret';

    /**
     * A list of local symbols
     * @var Symbol[]
     */
    private array $locals;

    /**
     * The current context (Expression, Statement or Return)
     */
    private string $context;

    /**
     * A mapping of local variables to shadowed names
     * @var Symbol[]
     */
    private array $shadowed;

    /**
     * A list of RecurFrame
     * @var array<RecurFrame|null>
     */
    private array $recurFrames;

    /**
     * A variable this is bound to
     */
    private string $boundTo;

    /**
     * Def inside of def should not work. This flag help us to keep track of this.
     */
    private bool $defAllowed = true;

    /**
     * @param Symbol[] $locals A list of local symbols
     * @param string $context The current context (Expression, Statement or Return)
     * @param Symbol[] $shadowed A list of shadowed variables
     * @param array<RecurFrame|null> $recurFrames A list of RecurFrame
     * @param string|null $boundTo A variable this is bound to
     */
    public function __construct(
        array $locals,
        string $context,
        array $shadowed,
        array $recurFrames,
        ?string $boundTo = null
    ) {
        $this->locals = $locals;
        $this->context = $context;
        $this->shadowed = $shadowed;
        $this->recurFrames = $recurFrames;
        $this->boundTo = $boundTo ?? '';
    }

    public static function empty(): NodeEnvironment
    {
        return new NodeEnvironment([], self::CTX_STMT, [], []);
    }

    /**
     * @return Symbol[]
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
     * Gets the shadowed name of a local variable
     *
     * @param Symbol $local The local variable
     *
     * @return Symbol|null
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

    public function getContext(): string
    {
        return $this->context;
    }

    public function withMergedLocals(array $locals): NodeEnvironment
    {
        $allLocalSymbols = array_merge(
            $this->locals,
            array_map(
                fn (Symbol $s) => Symbol::create($s->getName()),
                $locals
            )
        );

        return $this->withLocals(array_unique($allLocalSymbols));
    }

    public function withShadowedLocal(Symbol $local, Symbol $shadow): NodeEnvironment
    {
        $result = clone $this;
        $result->shadowed = array_merge($this->shadowed, [$local->getName() => $shadow]);

        return $result;
    }

    public function withLocals(array $locals): NodeEnvironment
    {
        $result = clone $this;
        $result->locals = $locals;

        return $result;
    }

    public function withContext(string $context): NodeEnvironment
    {
        $result = clone $this;
        $result->context = $context;

        return $result;
    }

    public function withAddedRecurFrame(RecurFrame $frame): NodeEnvironment
    {
        $result = clone $this;
        $result->recurFrames = array_merge($this->recurFrames, [$frame]);

        return $result;
    }

    public function withDisallowRecurFrame(): NodeEnvironment
    {
        $result = clone $this;
        $result->recurFrames = array_merge($this->recurFrames, [null]);

        return $result;
    }

    public function withBoundTo(string $boundTo): NodeEnvironment
    {
        $result = clone $this;
        $result->boundTo = $boundTo;

        return $result;
    }

    public function withDefAllowed(bool $defAllowed): NodeEnvironment
    {
        $result = clone $this;
        $result->defAllowed = $defAllowed;

        return $result;
    }

    public function getCurrentRecurFrame(): ?RecurFrame
    {
        if (empty($this->recurFrames)) {
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
}
