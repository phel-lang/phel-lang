<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Shared\Facade\CommandFacadeInterface;

final readonly class EntryPointDetector
{
    private const array CANDIDATES = ['main.phel', 'core.phel'];

    public function __construct(
        private CommandFacadeInterface $commandFacade,
    ) {}

    public function detect(): ?string
    {
        foreach ($this->commandFacade->getSourceDirectories() as $srcDir) {
            foreach (self::CANDIDATES as $entryFile) {
                $path = $srcDir . '/' . $entryFile;
                if (file_exists($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}
