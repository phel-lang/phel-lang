<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Phel\Shared\Facade\ConsoleFacadeInterface;

/**
 * @extends AbstractFacade<ConsoleFactory>
 */
#[ServiceMap(method: 'getFactory', className: ConsoleFactory::class)]
final class ConsoleFacade extends AbstractFacade implements ConsoleFacadeInterface
{
    public function getVersion(): string
    {
        return $this->getFactory()
            ->createVersionResolver()
            ->resolve();
    }

    public function runConsole(): void
    {
        $this->getFactory()
            ->createConsoleBootstrap()
            ->run();
    }
}
