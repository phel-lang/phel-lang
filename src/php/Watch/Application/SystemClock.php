<?php

declare(strict_types=1);

namespace Phel\Watch\Application;

use Phel\Watch\Domain\ClockInterface;

use function microtime;
use function usleep;

final class SystemClock implements ClockInterface
{
    public function nowMs(): int
    {
        return (int) (microtime(true) * 1000.0);
    }

    public function sleepMs(int $ms): void
    {
        if ($ms <= 0) {
            return;
        }

        usleep($ms * 1000);
    }
}
