<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler\Fixtures;

/**
 * Lower-bound reference: the inliner output after constant folding has
 * collapsed `(+ 5 1)` etc. to their literal results. Quantifies the
 * upper ceiling for what inlining + folding could reach, so the delta
 * between `inlined` and `direct` tells us how much remaining arithmetic
 * survives the call site after folding cannot fire (non-literal args).
 */
final class CallInlineDirect
{
    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public function __invoke(): array
    {
        return [6, 10, -5];
    }
}
