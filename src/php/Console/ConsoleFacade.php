<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractFacade;
use Phel\Shared\Facade\ConsoleFacadeInterface;

/**
 * @extends AbstractFacade<ConsoleFactory>
 */
final class ConsoleFacade extends AbstractFacade implements ConsoleFacadeInterface
{
    public function getVersion(): string
    {
        return $this->getFactory()
            ->createVersionFinder()
            ->getVersion();
    }

    public function runConsole(): void
    {
        $this->getFactory()
            ->createConsoleBootstrap()
            ->run();
    }
}
