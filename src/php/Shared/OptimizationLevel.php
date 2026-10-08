<?php

declare(strict_types=1);

namespace Phel\Shared;

use InvalidArgumentException;

use function max;
use function putenv;

/**
 * The optimization level a process compiles at: `PHEL_OPTIMIZATION_LEVEL` when
 * set, the project config's otherwise.
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

    /**
     * @internal
     */
    public static function pin(int $level): void
    {
        putenv(self::PIN_ENV . '=' . max(0, $level));
    }

    /**
     * @throws InvalidArgumentException when the pin is not a non-negative integer
     */
    public static function resolve(mixed $configured): int
    {
        return EnvVar::integer(self::PIN_ENV, 0)
            ?? max(0, ScalarCoercion::toInt($configured, CompileOptions::DEFAULT_OPTIMIZATION_LEVEL));
    }
}
