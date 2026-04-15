<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Run\Domain\Test\TestCommandOptions;
use Phel\Run\RunFacade;
use Phel\Shared\ResourceUsageFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function count;
use function sprintf;

/**
 * @method RunFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: RunFacade::class)]
final class TestCommand extends Command
{
    use ServiceResolverAwareTrait;

    public const string COMMAND_NAME = 'test';

    private const string ARG_PATHS = 'paths';

    private const string OPT_FILTER = 'filter';

    private const string OPT_TESTDOX = 'testdox';

    private const string OPT_FAIL_FAST = 'fail-fast';

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription(
                'Tests the given files. If no filenames are provided all tests in the "tests" directory are executed',
            )
            ->addArgument(
                self::ARG_PATHS,
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The file paths that you want to test.',
                [],
            )->addOption(
                self::OPT_FILTER,
                'f',
                InputOption::VALUE_OPTIONAL,
                'Filter by test names.',
            )->addOption(
                self::OPT_TESTDOX,
                null,
                InputOption::VALUE_NONE,
                'Report test execution progress in TestDox format.',
            )->addOption(
                self::OPT_FAIL_FAST,
                null,
                InputOption::VALUE_NONE,
                'Stop running tests after the first failure or error.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            /** @var list<string> $paths */
            $paths = (array) $input->getArgument(self::ARG_PATHS);
            $namespacesInformation = $this->getFacade()->getDependenciesFromPaths($paths);
            $failFast = (bool) $input->getOption(self::OPT_FAIL_FAST);

            // Suppress output during file loading phase and filter out integration test fixtures
            ob_start();
            $filteredNamespaces = [];
            /** @var list<array{0: NamespaceInformation, 1: Throwable}> $compileErrors */
            $compileErrors = [];
            foreach ($namespacesInformation as $info) {
                // Skip integration test fixture files - they are for PHPUnit tests only
                if (str_contains($info->getFile(), 'tests/php/Integration/')) {
                    continue;
                }

                if (str_contains($info->getFile(), 'tests/php/Benchmark/')) {
                    continue;
                }

                try {
                    $this->getFacade()->evalFile($info);
                    $filteredNamespaces[] = $info;
                } catch (Throwable $e) {
                    if ($failFast) {
                        ob_end_clean();
                        throw $e;
                    }

                    $compileErrors[] = [$info, $e];
                }
            }

            ob_end_clean();

            $this->reportCompileErrors($output, $compileErrors);

            if ($filteredNamespaces === []) {
                return ($compileErrors === []) ? self::SUCCESS : self::FAILURE;
            }

            $phelCode = $this->generatePhelTestCode($input, $filteredNamespaces);
            $compileOptions = (new CompileOptions())->setIsEnabledSourceMaps(false);
            $result = $this->getFacade()->eval($phelCode, $compileOptions);

            $output->writeln((new ResourceUsageFormatter())->resourceUsageSinceStartOfRequest());

            if ($compileErrors !== []) {
                return self::FAILURE;
            }

            return ($result) ? self::SUCCESS : self::FAILURE;
        } catch (CompilerException $e) {
            $this->getFacade()->writeLocatedException($output, $e);
        } catch (Throwable $e) {
            $this->getFacade()->writeStackTrace($output, $e);
        }

        return self::FAILURE;
    }

    /**
     * @param list<array{0: NamespaceInformation, 1: Throwable}> $compileErrors
     */
    private function reportCompileErrors(OutputInterface $output, array $compileErrors): void
    {
        if ($compileErrors === []) {
            return;
        }

        foreach ($compileErrors as [$info, $e]) {
            $output->writeln(sprintf('<error>Failed to compile %s</error>', $info->getFile()));
            if ($e instanceof CompilerException) {
                $this->getFacade()->writeLocatedException($output, $e);
            } else {
                $output->writeln($e->getMessage());
            }
        }

        $output->writeln(sprintf(
            '<comment>Skipped %d file(s) due to compile errors; continuing with the rest.</comment>',
            count($compileErrors),
        ));
    }

    private function generatePhelTestCode(InputInterface $input, array $namespacesInformation): string
    {
        return sprintf(
            '(do (phel\test/run-tests %s %s) (phel\test/successful?))',
            TestCommandOptions::fromArray([
                TestCommandOptions::FILTER => (string) $input->getOption(self::OPT_FILTER),
                TestCommandOptions::TESTDOX => (bool) $input->getOption(self::OPT_TESTDOX),
                TestCommandOptions::FAIL_FAST => (bool) $input->getOption(self::OPT_FAIL_FAST),
            ])->asPhelHashMap(),
            $this->namespacesAsString($namespacesInformation),
        );
    }

    /**
     * @param list<NamespaceInformation> $namespacesInfo
     */
    private function namespacesAsString(array $namespacesInfo): string
    {
        $namespaces = [];
        foreach ($namespacesInfo as $info) {
            $namespaces[] = "'" . $info->getNamespace();
        }

        return implode(' ', $namespaces);
    }
}
