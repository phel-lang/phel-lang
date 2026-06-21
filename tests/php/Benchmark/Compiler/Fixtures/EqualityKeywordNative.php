<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler\Fixtures;

use Phel\Lang\Keyword;

/**
 * Optimized emission shape for a `(= x :kw)`-heavy `cond` chain after
 * issue #2561: each keyword comparison lowers to a native PHP `===`
 * against a hoisted (interned) keyword constant — no registry lookup,
 * no `phel.core/=` `__invoke`.
 */
final readonly class EqualityKeywordNative
{
    private Keyword $a;

    private Keyword $b;

    private Keyword $c;

    public function __construct()
    {
        $this->a = Keyword::create('alpha');
        $this->b = Keyword::create('beta');
        $this->c = Keyword::create('gamma');
    }

    public function __invoke(Keyword $x): int
    {
        if ($x === $this->a) {
            return 1;
        }

        if ($x === $this->b) {
            return 2;
        }

        if ($x === $this->c) {
            return 3;
        }

        return 0;
    }
}
