<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\AbstractFacade;

/**
 * @method CommandFactory getFactory()
 */
final class CommandFacade extends AbstractFacade implements CommandFacadeInterface
{
    private const SUCCESS_CODE = 0;
    private const FAILED_CODE = 1;

    public function executeReplCommand(): void
    {
        $this->getFactory()
            ->createReplCommand()
            ->run();
    }

    public function executeRunCommand(string $fileOrPath): void
    {
        $this->getFactory()
            ->createRunCommand()
            ->run($fileOrPath);
    }

    /**
     * @param list<string> $paths
     */
    public function executeTestCommand(array $paths): void
    {
        $result = $this->getFactory()
            ->createTestCommand()
            ->run($paths);

        ($result)
            ? exit(self::SUCCESS_CODE)
            : exit(self::FAILED_CODE);
    }

    /**
     * @param list<string> $paths
     */
    public function executeFormatCommand(array $paths): void
    {
        $this->getFactory()
            ->createFormatCommand()
            ->run($paths);
    }

    public function executeExportCommand(): void
    {
        $this->getFactory()
            ->createExportCommand()
            ->run();
    }
}
