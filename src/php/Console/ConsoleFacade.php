<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractFacade;

/**
 * @method ConsoleFactory getFactory()
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
