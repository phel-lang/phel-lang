<?php

declare(strict_types=1);

namespace Phel;

use Phel\Command\CommandConfig;
use Phel\Command\CommandFacade;
use Phel\Command\CommandFactory;
use Phel\Compiler\CompilerFactory;
use Phel\Formatter\FormatterFactory;
use Phel\Interop\InteropConfig;
use Phel\Interop\InteropFactory;

final class PhelFacadeFactory
{
    private string $projectRootDir;

    public function __construct(string $projectRootDir)
    {
        $this->projectRootDir = $projectRootDir;
    }

    public function createPhelFacade(): PhelFacade
    {
        return new PhelFacade($this->createCommandFacade());
    }

    private function createCommandFacade(): CommandFacade
    {
        $compilerFactory = new CompilerFactory();

        return new CommandFacade(
            $this->projectRootDir,
            new CommandFactory(
                $this->projectRootDir,
                new CommandConfig($this->projectRootDir),
                $compilerFactory,
                new FormatterFactory($compilerFactory),
                new InteropFactory(new InteropConfig($this->projectRootDir))
            )
        );
    }
}
