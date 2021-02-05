<?php

declare(strict_types=1);

namespace Phel\Command;

interface CommandFacadeInterface
{
    public function executeReplCommand(): void;

    public function executeRunCommand(string $fileOrPath): void;

    /**
     * @param list<string> $paths
     */
    public function executeTestCommand(array $paths): void;

    /**
     * @param list<string> $paths
     */
    public function executeFormatCommand(array $paths): void;

    /**
     * @param list<string> $paths
     */
    public function executeExportCommand(array $paths): void;
}
