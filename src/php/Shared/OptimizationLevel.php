<?php

declare(strict_types=1);

namespace Phel\Shared;

use InvalidArgumentException;

use function getenv;
use function max;
use function preg_match;
use function putenv;
use function sprintf;

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

    public static function pin(int $level): void
    {
        putenv(self::PIN_ENV . '=' . max(0, $level));
    }

    /**
     * @throws InvalidArgumentException when the pin is not a non-negative integer
     */
    public static function resolve(mixed $configured): int
    {
        $pinned = getenv(self::PIN_ENV);
        if ($pinned === false || $pinned === '') {
            return max(0, ScalarCoercion::toInt($configured, CompileOptions::DEFAULT_OPTIMIZATION_LEVEL));
        }

        // A typo must not quietly compile at another level than the one asked for.
        if (preg_match('/^\d+$/', $pinned) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '%s must be a non-negative integer such as 0 or 2, got "%s".',
                self::PIN_ENV,
                $pinned,
            ));
        }

        return (int) $pinned;
    }
}
