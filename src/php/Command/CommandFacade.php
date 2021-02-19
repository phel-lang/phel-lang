<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Runtime\RuntimeInterface;
use RuntimeException;

final class CommandFacade implements CommandFacadeInterface
{
    private string $projectRootDir;
    private CommandFactoryInterface $commandFactory;

    public function __construct(
        string $projectRootDir,
        CommandFactoryInterface $commandFactory
    ) {
        $this->projectRootDir = $projectRootDir;
        $this->commandFactory = $commandFactory;
    }

    public function executeReplCommand(): void
    {
        $this->commandFactory
            ->createReplCommand($this->loadVendorPhelRuntime())
            ->run();
    }

    public function executeRunCommand(string $fileOrPath): void
    {
        $this->commandFactory
            ->createRunCommand($this->loadVendorPhelRuntime())
            ->run($fileOrPath);
    }

    /**
     * @param list<string> $paths
     */
    public function executeTestCommand(array $paths): void
    {
        $this->commandFactory
            ->createTestCommand($this->loadVendorPhelRuntime())
            ->run($paths);
    }

    /**
     * @param list<string> $paths
     */
    public function executeFormatCommand(array $paths): void
    {
        $this->commandFactory
            ->createFormatCommand()
            ->run($paths);
    }

    public function executeExportCommand(): void
    {
        $this->commandFactory
            ->createExportCommand($this->loadVendorPhelRuntime())
            ->run();
    }

    private function loadVendorPhelRuntime(): RuntimeInterface
    {
        $runtimePath = $this->projectRootDir
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'PhelRuntime.php';

        if (!file_exists($runtimePath)) {
            throw new RuntimeException('The Runtime could not be loaded from: ' . $runtimePath);
        }

        return require $runtimePath;
    }
}
