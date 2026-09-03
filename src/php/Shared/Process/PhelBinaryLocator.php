<?php

declare(strict_types=1);

namespace Phel\Shared\Process;

use Phel\Shared\ScalarCoercion;

use function is_array;

/**
 * The `phel` entry point of the current process, so a worker subprocess runs
 * the same code and the same project configuration as its parent.
 *
 * Shared by `phel test --parallel` and `phel mutate --parallel`, whose
 * factories may not reach into each other's module.
 */
final class PhelBinaryLocator
{
    public static function locate(): string
    {
        $script = ScalarCoercion::toString($_SERVER['SCRIPT_FILENAME'] ?? null);
        if ($script !== '') {
            return $script;
        }

        $argv = $_SERVER['argv'] ?? null;
        $firstArg = is_array($argv) ? ScalarCoercion::toString($argv[0] ?? null) : '';
        if ($firstArg !== '') {
            return $firstArg;
        }

        return __DIR__ . '/../../../../bin/phel';
    }
}
