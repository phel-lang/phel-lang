<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Infrastructure\Service\DebugLineTap;

final class DebugLineTapController
{
    public function enable(?string $phelFileFilter = null, string $logPath = './phel-debug.log'): void
    {
        DebugLineTap::enable($phelFileFilter, $logPath);
    }

    public function disable(): void
    {
        DebugLineTap::disable();
    }
}
