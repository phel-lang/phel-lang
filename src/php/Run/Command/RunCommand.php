<?php

declare(strict_types=1);

namespace Phel\Run\Command;

use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Run\Runner\NamespaceRunnerInterface;
use SebastianBergmann\Timer\ResourceUsageFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class RunCommand extends Command
{
    public const COMMAND_NAME = 'run';

    private CommandFacadeInterface $commandFacade;
    private NamespaceRunnerInterface $namespaceRunner;
    private BuildFacadeInterface $buildFacade;

    public function __construct(
        CommandFacadeInterface $commandFacade,
        NamespaceRunnerInterface $namespaceRunner,
        BuildFacadeInterface $buildFacade
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->commandFacade = $commandFacade;
        $this->namespaceRunner = $namespaceRunner;
        $this->buildFacade = $buildFacade;
    }

    protected function configure(): void
    {
        $this->setDescription('Runs a script.')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'The file path that you want to run.'
            )->addArgument(
                'argv',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Optional arguments',
                []
            )->addOption(
                'with-time',
                't',
                InputOption::VALUE_NONE,
                'With time awareness'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            /** @var string $fileOrPath */
            $fileOrPath = $input->getArgument('path');

            $namespace = $fileOrPath;
            if (file_exists($fileOrPath)) {
                $namespace = $this->buildFacade->getNamespaceFromFile($fileOrPath)->getNamespace();
            }
            $this->namespaceRunner->run($namespace);

            if ($input->getOption('with-time')) {
                $output->writeln((new ResourceUsageFormatter())->resourceUsageSinceStartOfRequest());
            }

            return self::SUCCESS;
        } catch (CompilerException $e) {
            $this->commandFacade->writeLocatedException($output, $e->getNestedException(), $e->getCodeSnippet());
        } catch (Throwable $e) {
            $this->commandFacade->writeStackTrace($output, $e);
        }

        return self::FAILURE;
    }
}
