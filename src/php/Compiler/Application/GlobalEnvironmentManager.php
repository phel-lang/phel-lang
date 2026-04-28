<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;

final class GlobalEnvironmentManager
{
    public function initialize(): void
    {
        GlobalEnvironmentSingleton::ensureInitialized();
    }

    public function reset(): void
    {
        GlobalEnvironmentSingleton::reset();
    }
}
