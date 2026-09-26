<?php

declare(strict_types=1);

namespace Phel\Lang;

use BadFunctionCallException;

use function get_debug_type;
use function is_callable;
use function sprintf;

/**
 * An `AbstractFn` standing in for a value that is not one: a closure, a
 * keyword, a collection used as a fn, anything a global was (re)defined to.
 *
 * Build-mode call sites cache the callee in a `$__phel_call_N` slot filled by
 * {@see \Phel::fnSlot()}, and call `invokeArityN` on it without asking what it
 * is (#3354). Wrapping everything else here keeps that call valid: each
 * `invokeArityN` inherited from `AbstractFn` lands in `__invoke`, which calls
 * the wrapped value. A value that cannot be called raises
 * `BadFunctionCallException` naming its type.
 *
 * Part of the public PHP API: compiled code constructs it through
 * `\Phel::fnSlot()`.
 */
final class ForeignFn extends AbstractFn
{
    public function __construct(
        private readonly mixed $fn,
    ) {}

    public function __invoke(mixed ...$args): mixed
    {
        $fn = $this->fn;
        if (!is_callable($fn)) {
            throw new BadFunctionCallException(sprintf('Cannot call a value of type %s', get_debug_type($fn)));
        }

        return $fn(...$args);
    }
}
