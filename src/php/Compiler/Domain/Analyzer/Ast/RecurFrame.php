<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Lang\Symbol;

final class RecurFrame
{
    private bool $isActive = false;

    /** @var list<Symbol> */
    private readonly array $shadows;

    /**
     * @param list<Symbol>  $params  the original binding/param symbols (used for arity and tag checks)
     * @param ?list<Symbol> $shadows The PHP-variable symbols a `recur` must assign to, one per param.
     *                               For `loop` these are the binding shadow names; for `fn` they are the
     *                               params themselves (fn params are not shadow-renamed). Defaults to
     *                               `$params` so `recur` targets the frame's own variables rather than
     *                               whatever binding currently shadows the name at the recur site.
     */
    public function __construct(
        private readonly array $params,
        ?array $shadows = null,
    ) {
        $this->shadows = $shadows ?? $params;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @return list<Symbol>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * The PHP-variable symbols a `recur` must assign to, one per param.
     *
     * @return list<Symbol>
     */
    public function getShadows(): array
    {
        return $this->shadows;
    }
}
