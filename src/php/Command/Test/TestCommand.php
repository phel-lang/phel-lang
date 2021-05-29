<?php

declare(strict_types=1);

namespace Phel\Command\Test;

use Phel\Command\Shared\CommandExceptionWriterInterface;
use Phel\Command\Test\Exceptions\CannotFindAnyTestsException;
use Phel\Compiler\Analyzer\Ast\NsNode;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
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
    /** @var list<string> */
    private array $defaultTestDirectories;

    public function __construct(
        CommandExceptionWriterInterface $exceptionWriter,
        RuntimeFacadeInterface $runtimeFacade,
        CompilerFacadeInterface $compilerFacade,
        array $testDirectories
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->exceptionWriter = $exceptionWriter;
        $this->runtimeFacade = $runtimeFacade;
        $this->compilerFacade = $compilerFacade;
        $this->defaultTestDirectories = $testDirectories;
    }

    protected function configure(): void
    {
        $this->setDescription('Tests the given files. If no filenames are provided all tests in the "tests" directory are executed.')
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The file paths that you want to format.',
                []
            )->addOption(
                'filter',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Filter by test names.'
            );
    }

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

            /** @psalm-suppress PossiblyInvalidCast */
            $result = $this->evalNamespaces($paths, TestCommandOptions::fromArray([
                TestCommandOptions::FILTER => (string)$input->getOption('filter'),
            ]));

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
     * @param list<string> $paths
     *
     * @throws CannotFindAnyTestsException
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     *
     * @return bool true if all tests were successful. False otherwise.
     */
    private function evalNamespaces(array $paths, TestCommandOptions $options): bool
    {
        $namespaces = $this->getNamespacesFromPaths($paths);

        if (empty($namespaces)) {
            throw CannotFindAnyTestsException::inPaths($paths);
        }

        $this->runtimeFacade->getRuntime()->loadNs('phel\test');

        $phelCode = sprintf(
            '(do (phel\test/run-tests %s %s) (successful?))',
            $options->asPhelHashMap(),
            $this->namespacesAsString($namespaces),
        );

        return $this->compilerFacade->eval($phelCode);
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
                fn (NsNode $node): string => $node->getNamespace(),
                $this->compilerFacade->extractNamespaceFromDirectories($this->defaultTestDirectories)
            );
        }

        return array_map(
            fn (string $filename): string => $this->compilerFacade->extractNamespaceFromFile($filename)->getNamespace(),
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
