<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler\Fixtures;

/**
 * Hypothetical inliner output for the same three call sites (issue
 * #2135). Each `defn` body — `(+ x 1)`, `(* x 2)`, `(- x)` — is spliced
 * directly into the call site with the literal argument substituted.
 * No `AbstractFn` dispatch, no static-slot read, no PHP frame.
 *
 * Still does the arithmetic per call: the constant-folder peephole that
 * would collapse `(5 + 1)` to `6` is measured separately by the `direct`
 * subject below.
 */
final class CallInlineInlined
{
    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public function __invoke(): array
    {
        return [
            (5 + 1),
            (5 * 2),
            (-5),
        ];
    }
}
