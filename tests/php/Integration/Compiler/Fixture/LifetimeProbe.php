<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Fixture;

/**
 * Counts the instances still alive, so a caller can tell when PHP released
 * the last reference to one.
 */
final class LifetimeProbe
{
    public static int $alive = 0;

    public function __construct()
    {
        ++self::$alive;
    }

    public function __destruct()
    {
        --self::$alive;
    }
}
