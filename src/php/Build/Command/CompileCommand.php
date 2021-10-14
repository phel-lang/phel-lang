<?php

declare(strict_types=1);

namespace Phel\Build\Command;

use Phel\Build\Compile\ProjectCompiler;
use Phel\Command\CommandFacadeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CompileCommand extends Command
{
    public const COMMAND_NAME = 'compile';

    private ProjectCompiler $projectCompiler;
    private CommandFacadeInterface $commandFacade;

    public function __construct(
        ProjectCompiler $projectCompiler,
        CommandFacadeInterface $commandFacade
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->projectCompiler = $projectCompiler;
        $this->commandFacade = $commandFacade;
    }

    protected function configure(): void
    {
        $this->setDescription('Compile the current project');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->projectCompiler->compileProject(
            [
                ...$this->commandFacade->getSourceDirectories(),
                ...$this->commandFacade->getVendorSourceDirectories(),
            ],
            $this->commandFacade->getOutputDirectory()
        );

        return self::SUCCESS;
    }
}
