<?php

declare(strict_types=1);

namespace Phel\Build\Command;

use Phel\Build\Compile\BuildOptions;
use Phel\Build\Compile\ProjectCompiler;
use Phel\Command\CommandFacadeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CompileCommand extends Command
{
    public const COMMAND_NAME = 'compile';
    private const OPTION_NO_CACHE = 'no-cache';
    private const OPTION_NO_SOURCE_MAP = 'no-source-map';

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
        $this->setDescription('Compile the current project')
            ->addOption(self::OPTION_NO_CACHE, null, InputOption::VALUE_NONE, 'Disables cache')
            ->addOption(self::OPTION_NO_SOURCE_MAP, null, InputOption::VALUE_NONE, 'Disables source maps');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $buildOptions = $this->getBuildOptions($input);

        $this->projectCompiler->compileProject(
            [
                ...$this->commandFacade->getSourceDirectories(),
                ...$this->commandFacade->getVendorSourceDirectories(),
            ],
            $this->commandFacade->getOutputDirectory(),
            $buildOptions
        );

        return self::SUCCESS;
    }

    private function getBuildOptions(InputInterface $input): BuildOptions
    {
        $enableCache = $input->getOption(self::OPTION_NO_CACHE) !== true;
        $enableSourceMap = $input->getOption(self::OPTION_NO_SOURCE_MAP) !== true;

        return new BuildOptions($enableCache, $enableSourceMap);
    }
}
