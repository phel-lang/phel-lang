<?php

declare(strict_types=1);

namespace Phel\Build\Command;

use Phel\Build\Compile\BuildOptions;
use Phel\Build\Compile\ProjectCompiler;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\Exceptions\CompilerException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class CompileCommand extends Command
{
    public const COMMAND_NAME = 'compile';

    private const OPTION_CACHE = 'cache';
    private const OPTION_SOURCE_MAP = 'source-map';

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
            ->addOption(self::OPTION_CACHE, null, InputOption::VALUE_NEGATABLE, 'Enable cache', true)
            ->addOption(self::OPTION_SOURCE_MAP, null, InputOption::VALUE_NEGATABLE, 'Enable source maps', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandFacade->registerExceptionHandler();

        $buildOptions = $this->getBuildOptions($input);

        try {
            $this->projectCompiler->compileProject(
                [
                ...$this->commandFacade->getSourceDirectories(),
                ...$this->commandFacade->getVendorSourceDirectories(),
            ],
                $this->commandFacade->getOutputDirectory(),
                $buildOptions
            );
        } catch (CompilerException $e) {
            $this->commandFacade->writeLocatedException($output, $e->getNestedException(), $e->getCodeSnippet());
        } catch (Throwable $e) {
            $this->commandFacade->writeStackTrace($output, $e);
        }

        return self::SUCCESS;
    }

    private function getBuildOptions(InputInterface $input): BuildOptions
    {
        return new BuildOptions(
            $input->getOption(self::OPTION_CACHE) === true,
            $input->getOption(self::OPTION_SOURCE_MAP) === true
        );
    }
}
