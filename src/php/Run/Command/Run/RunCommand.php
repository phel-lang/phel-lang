<?php

declare(strict_types=1);

namespace Phel\Run\Command\Run;

use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Config\ConfigFacadeInterface;
use Phel\Run\Command\Run\Exceptions\CannotLoadNamespaceException;
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
    private BuildFacadeInterface $buildFacade;

    # TODO: move this config thing inside this Run module
    private ConfigFacadeInterface $configFacade;

    public function __construct(
        CommandFacadeInterface $commandFacade,
        BuildFacadeInterface $buildFacade,
        ConfigFacadeInterface $configFacade
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->commandFacade = $commandFacade;
        $this->buildFacade = $buildFacade;
        $this->configFacade = $configFacade;
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
            $this->loadNamespace($fileOrPath);

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

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     * @throws CannotLoadNamespaceException
     */
    private function loadNamespace(string $fileOrPath): void
    {
        $namespace = $fileOrPath;
        if (file_exists($fileOrPath)) {
            $namespace = $this->buildFacade->getNamespaceFromFile($fileOrPath)->getNamespace();
        }

        $namespaceInformation = $this->buildFacade->getDependenciesForNamespace(
            [
                ...$this->configFacade->getSourceDirectories(),
                ...$this->configFacade->getVendorSourceDirectories(),
            ],
            [$namespace, 'phel\\core']
        );

        foreach ($namespaceInformation as $info) {
            $this->buildFacade->evalFile($info->getFile());
        }
    }
}
