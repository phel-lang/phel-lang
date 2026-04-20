<?php

declare(strict_types=1);

namespace Phel\Fiber;

use Gacela\Framework\AbstractConfig;

final class FiberConfig extends AbstractConfig
{
    private const int DEFAULT_SLEEP_MICROSECONDS = 500;

    public static function defaultSleepMicroseconds(): int
    {
        return self::DEFAULT_SLEEP_MICROSECONDS;
    }
}
