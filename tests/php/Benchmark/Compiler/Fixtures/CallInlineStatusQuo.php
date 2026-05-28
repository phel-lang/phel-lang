<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler\Fixtures;

use Phel\Lang\AbstractFn;

/**
 * Status-quo emission for a call site to a single-expression pure `defn`.
 * Mirrors what `CallEmitter` produces today: a cached
 * `$__phel_call_N ??= \Phel::getDefinition(...)` lookup followed by a
 * direct `->__invoke($arg)` on the resolved `AbstractFn`.
 *
 * The registry lookup is paid once per fn body (the `??=` short-circuits
 * after the first call). The per-call cost we are measuring is the static
 * read + virtual `__invoke` dispatch + body execution.
 */
final class CallInlineStatusQuo
{
    private static ?AbstractFn $fnInc = null;

    private static ?AbstractFn $fnDouble = null;

    private static ?AbstractFn $fnNeg = null;

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public function __invoke(): array
    {
        return [
            (self::$fnInc)->__invoke(5),
            (self::$fnDouble)->__invoke(5),
            (self::$fnNeg)->__invoke(5),
        ];
    }

    public static function seed(): void
    {
        self::$fnInc ??= new class() extends AbstractFn {
            public function __invoke(int $x): int
            {
                return $x + 1;
            }
        };

        self::$fnDouble ??= new class() extends AbstractFn {
            public function __invoke(int $x): int
            {
                return $x * 2;
            }
        };

        self::$fnNeg ??= new class() extends AbstractFn {
            public function __invoke(int $x): int
            {
                return -$x;
            }
        };
    }
}
