<?php

declare(strict_types=1);

namespace Phel\Shared;

use function getenv;
use function max;
use function putenv;

/**
 * The optimization level a process compiles at: the project config's, unless
 * the process pinned one.
 *
 * A pin is an environment variable rather than static state, so it holds for
 * every compile in the process, the dependencies a compiled file loads on its
 * own included, and for the subprocesses it spawns. `phel mutate` pins 0: a
 * mutant is a redefined var, and at a higher level its callers inline the
 * original body and never reach it (#3396).
 */
final class OptimizationLevel
{
    public const string PIN_ENV = 'PHEL_OPTIMIZATION_LEVEL';

    public static function pin(int $level): void
    {
        putenv(self::PIN_ENV . '=' . max(0, $level));
    }

    public static function resolve(mixed $configured): int
    {
        $pinned = getenv(self::PIN_ENV);
        $level = $pinned === false || $pinned === '' ? $configured : $pinned;

        return max(0, ScalarCoercion::toInt($level, CompileOptions::DEFAULT_OPTIMIZATION_LEVEL));
    }
}
