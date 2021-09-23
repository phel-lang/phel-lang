<?php

declare(strict_types=1);

namespace Phel\Command\Test;

use Phel\Build\BuildFacadeInterface;
use Phel\Build\Extractor\NamespaceInformation;
use Phel\Command\Shared\CommandExceptionWriterInterface;
use Phel\Command\Test\Exceptions\CannotFindAnyTestsException;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Runtime\RuntimeFacadeInterface;
use SebastianBergmann\Timer\ResourceUsageFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class TestCommand extends Command
{
    public const COMMAND_NAME = 'test';

    private CommandExceptionWriterInterface $exceptionWriter;
    private RuntimeFacadeInterface $runtimeFacade;
    private CompilerFacadeInterface $compilerFacade;
    private BuildFacadeInterface $buildFacade;
    /** @var list<string> */
    private array $defaultTestDirectories;

    public function __construct(
        CommandExceptionWriterInterface $exceptionWriter,
        RuntimeFacadeInterface $runtimeFacade,
        CompilerFacadeInterface $compilerFacade,
        BuildFacadeInterface $buildFacade,
        array $testDirectories
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->exceptionWriter = $exceptionWriter;
        $this->runtimeFacade = $runtimeFacade;
        $this->compilerFacade = $compilerFacade;
        $this->buildFacade = $buildFacade;
        $this->defaultTestDirectories = $testDirectories;
    }

    protected function configure(): void
    {
        $this->setDescription('Tests the given files. If no filenames are provided all tests in the "tests" directory are executed.')
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The file paths that you want to test.',
                []
            )->addOption(
                'filter',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Filter by test names.'
            );
    }

    /**
     * @internal for testing
     */
    public function addRuntimePath(string $namespacePrefix, array $path): self
    {
        $this->runtimeFacade->addPath($namespacePrefix, $path);

        return $this;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            /** @var list<string> $paths */
            $paths = (array)$input->getArgument('paths');

            $namespaces = $this->getNamespacesFromPaths($paths);
            if (empty($namespaces)) {
                throw CannotFindAnyTestsException::inPaths($paths);
            }
            $namespaces[] = 'phel\\test';

            $srcDirectories = $this->runtimeFacade->getSourceDirectories();
            $namespaceInformation = $this->buildFacade->getDependenciesForNamespace($srcDirectories, $namespaces);

            foreach ($namespaceInformation as $info) {
                $this->buildFacade->evalFile($info->getFile());
            }

            $phelCode = sprintf(
                '(do (phel\test/run-tests %s %s) (phel\test/successful?))',
                TestCommandOptions::fromArray([
                    TestCommandOptions::FILTER => (string)$input->getOption('filter'),
                ])->asPhelHashMap(),
                $this->namespacesAsString($namespaces),
            );

            $result = $this->compilerFacade->eval($phelCode);

            $output->writeln((new ResourceUsageFormatter())->resourceUsageSinceStartOfRequest());

            return ($result) ? self::SUCCESS : self::FAILURE;
        } catch (CompilerException $e) {
            $this->exceptionWriter->writeLocatedException($output, $e->getNestedException(), $e->getCodeSnippet());
        } catch (Throwable $e) {
            $this->exceptionWriter->writeStackTrace($output, $e);
        }

        return self::FAILURE;
    }

    /**
     * @param string[] $paths
     *
     * @return string[]
     */
    private function getNamespacesFromPaths(array $paths): array
    {
        if (empty($paths)) {
            return array_map(
                static fn (NamespaceInformation $info): string => $info->getNamespace(),
                $this->buildFacade->getNamespaceFromDirectories($this->defaultTestDirectories)
            );
        }

        return array_map(
            fn (string $filename): string => $this->buildFacade->getNamespaceFromFile($filename)->getNamespace(),
            $paths
        );
    }

    private function namespacesAsString(array $namespaces): string
    {
        return implode(' ', array_map(
            static fn (string $ns): string => "'" . $ns,
            $namespaces
        ));
    }
}
