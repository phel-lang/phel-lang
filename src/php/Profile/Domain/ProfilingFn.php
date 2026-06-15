<?php

declare(strict_types=1);

namespace Phel\Profile\Domain;

use Override;
use Phel\Lang\AbstractFn;
use ReflectionClass;

use function is_string;

/**
 * Proxy that wraps a user `defn` so every call routed via `Registry`
 * is timed. Self-recursive calls are emitted by the compiler as
 * `$this(...)` rather than a registry lookup, so they never reach this
 * proxy and stay untimed; that bypass is a compiler emit detail, not a
 * constraint of this class (see commit bee78ffe).
 */
final class ProfilingFn extends AbstractFn
{
    private readonly string $boundTo;

    public function __construct(
        private readonly AbstractFn $inner,
        private readonly ProfilerSession $session,
    ) {
        $reflection = new ReflectionClass($inner);
        $constant = $reflection->hasConstant('BOUND_TO')
            ? $reflection->getConstant('BOUND_TO')
            : null;
        $this->boundTo = is_string($constant) && $constant !== '' ? $constant : '<anonymous>';
        $this->withMeta($inner->getMeta());
    }

    public function __invoke(mixed ...$args): mixed
    {
        $this->session->enter($this->boundTo);
        try {
            // AbstractFn declares __invoke via @method; psalm does not honor it.
            /** @psalm-suppress UndefinedMethod */
            return $this->inner->__invoke(...$args);
        } finally {
            $this->session->exit();
        }
    }

    #[Override]
    public function __toString(): string
    {
        return (string) $this->inner;
    }
}
