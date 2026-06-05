<?php

declare(strict_types=1);

namespace PhelTest\Support;

use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Isolates Gacela's per-project config cache before every test.
 * See {@see PerTestGacelaCache}.
 */
final class PerTestGacelaCacheExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $cache = new PerTestGacelaCache();

        $facade->registerSubscriber(
            new readonly class($cache) implements PreparationStartedSubscriber {
                public function __construct(private PerTestGacelaCache $cache) {}

                public function notify(PreparationStarted $event): void
                {
                    $this->cache->isolate();
                }
            },
        );
    }
}
