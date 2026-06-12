<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use ArrayAccess;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Run\Domain\Test\TestCommandOptions;
use Phel\Run\Domain\Test\TestNamespacePruner;
use Phel\Run\RunFacade;
use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\NamespaceInformation;
use Phel\Shared\ResourceUsageFormatter;
use Phel\Shared\ScalarCoercion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function count;
use function is_array;
use function sprintf;

/**
 * @method RunFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: RunFacade::class)]
final class TestCommand extends Command
{
    use ServiceResolverAwareTrait;

    public const string COMMAND_NAME = 'test';

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription(
                'Tests the given files. If no filenames are provided all tests in the "tests" directory are executed',
            )
            ->addArgument(
                TestCommandOptionParser::ARG_PATHS,
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The file paths that you want to test.',
                [],
            )->addOption(
                TestCommandOptionParser::OPT_FILTER,
                'f',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                "Regex or substring matched against test names. Repeatable; matches are OR'd.",
                [],
            )->addOption(
                TestCommandOptionParser::OPT_TESTDOX,
                null,
                InputOption::VALUE_NONE,
                'Report test execution progress in TestDox format. Shortcut for --reporter=testdox.',
            )->addOption(
                TestCommandOptionParser::OPT_FAIL_FAST,
                null,
                InputOption::VALUE_NONE,
                'Stop running tests after the first failure or error.',
            )->addOption(
                TestCommandOptionParser::OPT_STACK_TRACE,
                null,
                InputOption::VALUE_NONE,
                'Print the full PHP stack trace for each errored test.',
            )->addOption(
                TestCommandOptionParser::OPT_REPORTER,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Reporter to emit test events through. Repeatable. Built-ins: default, testdox, dot, tap, junit-xml.',
                [],
            )->addOption(
                TestCommandOptionParser::OPT_OUTPUT,
                null,
                InputOption::VALUE_REQUIRED,
                'Write the junit-xml reporter to a file instead of stdout.',
            )->addOption(
                TestCommandOptionParser::OPT_INCLUDE,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                "Tag name (e.g. integration). Only tests carrying this tag run. Repeatable; tags are OR'd.",
                [],
            )->addOption(
                TestCommandOptionParser::OPT_EXCLUDE,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Tag name (e.g. slow). Tests carrying this tag are skipped. Repeatable; wins over --include.',
                [],
            )->addOption(
                TestCommandOptionParser::OPT_NS,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                "Namespace glob (e.g. phel.http.*). Repeatable; globs are OR'd. `*` matches one segment, `**` any.",
                [],
            )->addOption(
                TestCommandOptionParser::OPT_LIST,
                null,
                InputOption::VALUE_NONE,
                'List discovered tests after applying filters/selectors. Does not run them.',
            )->addOption(
                TestCommandOptionParser::OPT_LAST_FAILED,
                null,
                InputOption::VALUE_NONE,
                'Re-run only tests that failed on the previous run. Reads `<phel-dir>/last-failed.txt` from the current project. Combine with --repeat to hammer flaky tests.',
            )->addOption(
                TestCommandOptionParser::OPT_SLOWEST,
                null,
                InputOption::VALUE_REQUIRED,
                'Print the N slowest tests after the summary. 0 disables.',
                0,
            )->addOption(
                TestCommandOptionParser::OPT_REPEAT,
                null,
                InputOption::VALUE_REQUIRED,
                'Run the selected tests N times in a row. Useful for catching flaky tests. Must be >= 1.',
                1,
            )->addOption(
                TestCommandOptionParser::OPT_SEED,
                null,
                InputOption::VALUE_REQUIRED,
                'Integer seed for the test-order RNG. Printed at the start of every randomized run so failures can be reproduced.',
            )->addOption(
                TestCommandOptionParser::OPT_RANDOM_ORDER,
                null,
                InputOption::VALUE_NONE,
                'Shuffle the order of tests within each namespace. Uses --seed when provided, otherwise picks a fresh random seed.',
            )->addOption(
                TestCommandOptionParser::OPT_PARALLEL,
                null,
                InputOption::VALUE_REQUIRED,
                'Run namespaces in parallel using subprocess workers. Accepts an integer worker count, "auto" (CPU detection capped at 8), or "max" (every core the kernel reports, uncapped). Auto-disabled for --reporter=tap, --list, or when a profiler hook is installed.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $optionParser = new TestCommandOptionParser();

        try {
            /** @var list<string> $paths */
            $paths = (array) $input->getArgument(TestCommandOptionParser::ARG_PATHS);
            $namespacesInformation = $this->getFacade()->getDependenciesFromPaths($paths);
            $failFast = (bool) $input->getOption(TestCommandOptionParser::OPT_FAIL_FAST);
            /** @var list<string> $nsPatterns */
            $nsPatterns = (array) $input->getOption(TestCommandOptionParser::OPT_NS);
            $namespacesInformation = new TestNamespacePruner()->prune($namespacesInformation, $nsPatterns);

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

            $workerCount = $optionParser->decideParallelism(
                $input,
                $output,
                $this->getFacade()->createCpuCountDetector(),
            );
            $options = $optionParser->collectOptions($input);

            if ($workerCount !== null) {
                $success = $this->getFacade()
                    ->createParallelTestOrchestrator()
                    ->run($filteredNamespaces, $options, $workerCount, $output);

                $output->writeln(new ResourceUsageFormatter()->resourceUsageSinceStartOfRequest());

                if ($compileErrors !== []) {
                    return self::FAILURE;
                }

                return $success ? self::SUCCESS : self::FAILURE;
            }

            $phelCode = $this->generatePhelTestCodeFromOptions($options, $filteredNamespaces);
            $compileOptions = new CompileOptions()->setIsEnabledSourceMaps(false);
            $result = $this->getFacade()->eval($phelCode, $compileOptions);

            $output->writeln(new ResourceUsageFormatter()->resourceUsageSinceStartOfRequest());

            if ($compileErrors !== []) {
                return self::FAILURE;
            }

            // `eval` returns the value of the generated test form, a Phel
            // vector `[successful? total]` (ArrayAccess), or a plain array.
            $successful = false;
            $total = 0;
            if (is_array($result) || $result instanceof ArrayAccess) {
                $successful = (bool) ($result[0] ?? false);
                $total = ScalarCoercion::toInt($result[1] ?? null);
            }

            if ($this->isNoMatchWithSelectors($total, $options, $paths)) {
                $output->writeln('<error>No tests matched the given paths or selectors.</error>');

                return self::FAILURE;
            }

            return $successful ? self::SUCCESS : self::FAILURE;
        } catch (CompilerException $e) {
            $this->getFacade()->writeLocatedException($output, $e);
        } catch (Throwable $e) {
            $this->getFacade()->writeStackTrace($output, $e);
        }

        return self::FAILURE;
    }

    /**
     * Returns true when the test set was explicitly narrowed AND zero tests ran.
     * Narrowing means explicit path arguments or selectors (--filter, --ns,
     * --include, --exclude, --last-failed / --only-tests). Structural options
     * (--fail-fast, --reporter, etc.) are ignored. Running zero tests against
     * an explicit narrowing must fail loudly instead of passing silently.
     *
     * @param array<string, mixed> $options
     * @param list<string>         $paths
     */
    private function isNoMatchWithSelectors(int $total, array $options, array $paths): bool
    {
        if ($total > 0) {
            return false;
        }

        // --list never executes tests, so the run-total is always zero there;
        // the listing itself is the result.
        if (!empty($options[TestCommandOptions::LIST_ONLY])) {
            return false;
        }

        $filters = $options[TestCommandOptions::FILTERS] ?? [];
        $nsPatterns = $options[TestCommandOptions::NS_PATTERNS] ?? [];
        $includes = $options[TestCommandOptions::INCLUDE] ?? [];
        $excludes = $options[TestCommandOptions::EXCLUDE] ?? [];
        $onlyTests = $options[TestCommandOptions::ONLY_TESTS] ?? [];

        return $paths !== []
            || $filters !== []
            || $nsPatterns !== []
            || $includes !== []
            || $excludes !== []
            || $onlyTests !== [];
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

    /**
     * @param array<string, mixed>       $options
     * @param list<NamespaceInformation> $namespacesInformation
     */
    private function generatePhelTestCodeFromOptions(array $options, array $namespacesInformation): string
    {
        return sprintf(
            '(do (phel\test/run-tests %s %s) [(phel\test/successful?) (get (get (phel\test/get-stats) :counts) :total)])',
            TestCommandOptions::fromArray($options)->asPhelHashMap(),
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
