<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Interop;

use Phel\Interop\PhelCallerTrait;

/**
 * Stands in for a class `phel export` generates: one trait call per exported
 * function, which is the only shape a PHP host ever calls Phel with.
 */
final class ExportedWrapperFixture
{
    use PhelCallerTrait;

    public static function identity(mixed $value): mixed
    {
        return self::callPhel('bench-embed\\host', 'identity', $value);
    }
}
