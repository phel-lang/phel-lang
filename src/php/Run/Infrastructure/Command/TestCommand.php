<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use InvalidArgumentException;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Lang\ProfilerHookInterface;
use Phel\Lang\Registry;
use Phel\Run\Domain\Test\TestCommandOptions;
use Phel\Run\Domain\Test\TestNamespacePruner;
use Phel\Run\RunFacade;
use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\PhelProjectDirectory;
use Phel\Shared\ResourceUsageFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function count;
use function getcwd;
use function in_array;
use function is_numeric;
use function is_string;
use function sprintf;
use function strtolower;

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

    private const string OPT_STACK_TRACE = 'stack-trace';

    private const string OPT_REPORTER = 'reporter';

    private const string OPT_OUTPUT = 'output';

    private const string OPT_INCLUDE = 'include';

    private const string OPT_EXCLUDE = 'exclude';

    private const string OPT_NS = 'ns';

    private const string OPT_LIST = 'list';

    private const string OPT_LAST_FAILED = 'last-failed';

    private const string OPT_SLOWEST = 'slowest';

    private const string OPT_REPEAT = 'repeat';

    private const string OPT_SEED = 'seed';

    private const string OPT_RANDOM_ORDER = 'random-order';

    private const string OPT_PARALLEL = 'parallel';

    private const string LAST_FAILED_FILENAME = 'last-failed.txt';

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
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                "Regex or substring matched against test names. Repeatable; matches are OR'd.",
                [],
            )->addOption(
                self::OPT_TESTDOX,
                null,
                InputOption::VALUE_NONE,
                'Report test execution progress in TestDox format. Shortcut for --reporter=testdox.',
            )->addOption(
                self::OPT_FAIL_FAST,
                null,
                InputOption::VALUE_NONE,
                'Stop running tests after the first failure or error.',
            )->addOption(
                self::OPT_STACK_TRACE,
                null,
                InputOption::VALUE_NONE,
                'Print the full PHP stack trace for each errored test.',
            )->addOption(
                self::OPT_REPORTER,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Reporter to emit test events through. Repeatable. Built-ins: default, testdox, dot, tap, junit-xml.',
                [],
            )->addOption(
                self::OPT_OUTPUT,
                null,
                InputOption::VALUE_REQUIRED,
                'Write the junit-xml reporter to a file instead of stdout.',
            )->addOption(
                self::OPT_INCLUDE,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                "Tag name (e.g. integration). Only tests carrying this tag run. Repeatable; tags are OR'd.",
                [],
            )->addOption(
                self::OPT_EXCLUDE,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Tag name (e.g. slow). Tests carrying this tag are skipped. Repeatable; wins over --include.',
                [],
            )->addOption(
                self::OPT_NS,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                "Namespace glob (e.g. phel.http.*). Repeatable; globs are OR'd. `*` matches one segment, `**` any.",
                [],
            )->addOption(
                self::OPT_LIST,
                null,
                InputOption::VALUE_NONE,
                'List discovered tests after applying filters/selectors. Does not run them.',
            )->addOption(
                self::OPT_LAST_FAILED,
                null,
                InputOption::VALUE_NONE,
                'Re-run only tests that failed on the previous run. Reads `<phel-dir>/last-failed.txt` from the current project. Combine with --repeat to hammer flaky tests.',
            )->addOption(
                self::OPT_SLOWEST,
                null,
                InputOption::VALUE_REQUIRED,
                'Print the N slowest tests after the summary. 0 disables.',
                0,
            )->addOption(
                self::OPT_REPEAT,
                null,
                InputOption::VALUE_REQUIRED,
                'Run the selected tests N times in a row. Useful for catching flaky tests. Must be >= 1.',
                1,
            )->addOption(
                self::OPT_SEED,
                null,
                InputOption::VALUE_REQUIRED,
                'Integer seed for the test-order RNG. Printed at the start of every randomized run so failures can be reproduced.',
            )->addOption(
                self::OPT_RANDOM_ORDER,
                null,
                InputOption::VALUE_NONE,
                'Shuffle the order of tests within each namespace. Uses --seed when provided, otherwise picks a fresh random seed.',
            )->addOption(
                self::OPT_PARALLEL,
                null,
                InputOption::VALUE_REQUIRED,
                'Run namespaces in parallel using subprocess workers. Accepts an integer worker count or "auto" (CPU detection, capped at 8). Auto-disabled for --reporter=tap, --list, or when a profiler hook is installed.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            /** @var list<string> $paths */
            $paths = (array) $input->getArgument(self::ARG_PATHS);
            $namespacesInformation = $this->getFacade()->getDependenciesFromPaths($paths);
            $failFast = (bool) $input->getOption(self::OPT_FAIL_FAST);
            /** @var list<string> $nsPatterns */
            $nsPatterns = (array) $input->getOption(self::OPT_NS);
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

            $workerCount = $this->decideParallelism($input, $output);
            $options = $this->collectOptions($input);

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

    /**
     * @param array<string, mixed>       $options
     * @param list<NamespaceInformation> $namespacesInformation
     */
    private function generatePhelTestCodeFromOptions(array $options, array $namespacesInformation): string
    {
        return sprintf(
            '(do (phel\test/run-tests %s %s) (phel\test/successful?))',
            TestCommandOptions::fromArray($options)->asPhelHashMap(),
            $this->namespacesAsString($namespacesInformation),
        );
    }

    /**
     * Returns the parallel worker count, or null when the run must stay
     * serial. Auto-disable rules:
     *  - `--parallel` not passed
     *  - `--parallel=1` (explicit one-worker run)
     *  - `--reporter=tap` (TAP requires monotonic counter across all tests)
     *  - `--list` (discovery only, no execution)
     *  - Registry profiler hook installed (counts run in parent only)
     */
    private function decideParallelism(InputInterface $input, OutputInterface $output): ?int
    {
        $raw = $input->getOption(self::OPT_PARALLEL);
        if ($raw === null || $raw === '') {
            return null;
        }

        $disabledReason = $this->parallelDisabledReason($input);
        if ($disabledReason !== null) {
            if ($output->isVerbose()) {
                $output->writeln(sprintf('<comment>Ignoring --parallel: %s.</comment>', $disabledReason));
            }

            return null;
        }

        if (is_string($raw) && strtolower($raw) === 'auto') {
            return $this->getFacade()->createCpuCountDetector()->detect();
        }

        if (!is_numeric($raw)) {
            throw new InvalidArgumentException(sprintf(
                '--parallel must be an integer >= 1 or "auto", got %s.',
                is_string($raw) ? $raw : (string) $raw,
            ));
        }

        $value = (int) $raw;
        if ($value < 1) {
            throw new InvalidArgumentException('--parallel must be >= 1.');
        }

        return $value === 1 ? null : $value;
    }

    private function parallelDisabledReason(InputInterface $input): ?string
    {
        if ((bool) $input->getOption(self::OPT_LIST)) {
            return '--list bypasses execution';
        }

        /** @var list<string> $reporters */
        $reporters = (array) $input->getOption(self::OPT_REPORTER);
        if (in_array('tap', $reporters, true)) {
            return 'TAP reporter requires a monotonic test counter';
        }

        if (Registry::$profilerHook instanceof ProfilerHookInterface) {
            return 'profiler hook only collects counts in the parent process';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectOptions(InputInterface $input): array
    {
        $output = $input->getOption(self::OPT_OUTPUT);
        $listOnly = (bool) $input->getOption(self::OPT_LIST);
        $lastFailed = (bool) $input->getOption(self::OPT_LAST_FAILED);

        $lastFailedFile = $this->lastFailedFilePath();

        if (!$listOnly && $lastFailedFile !== null) {
            PhelProjectDirectory::ensure((string) getcwd());
        }

        return [
            TestCommandOptions::FILTER => null,
            TestCommandOptions::TESTDOX => (bool) $input->getOption(self::OPT_TESTDOX),
            TestCommandOptions::FAIL_FAST => (bool) $input->getOption(self::OPT_FAIL_FAST),
            TestCommandOptions::STACK_TRACE => (bool) $input->getOption(self::OPT_STACK_TRACE),
            TestCommandOptions::REPORTERS => (array) $input->getOption(self::OPT_REPORTER),
            TestCommandOptions::JUNIT_OUTPUT => is_string($output) ? $output : null,
            TestCommandOptions::INCLUDE => (array) $input->getOption(self::OPT_INCLUDE),
            TestCommandOptions::EXCLUDE => (array) $input->getOption(self::OPT_EXCLUDE),
            TestCommandOptions::NS_PATTERNS => (array) $input->getOption(self::OPT_NS),
            TestCommandOptions::FILTERS => (array) $input->getOption(self::OPT_FILTER),
            TestCommandOptions::LIST_ONLY => $listOnly,
            TestCommandOptions::ONLY_TESTS => $lastFailed ? $this->readLastFailed() : [],
            TestCommandOptions::LAST_FAILED_FILE => $listOnly ? null : $lastFailedFile,
            TestCommandOptions::SLOWEST => (int) $input->getOption(self::OPT_SLOWEST),
            TestCommandOptions::REPEAT => $this->parseRepeat($input),
            TestCommandOptions::SEED => $this->parseSeed($input),
            TestCommandOptions::RANDOM_ORDER => (bool) $input->getOption(self::OPT_RANDOM_ORDER),
        ];
    }

    private function lastFailedFilePath(): ?string
    {
        $cwd = getcwd();
        if (!is_string($cwd)) {
            return null;
        }

        return PhelProjectDirectory::path($cwd, self::LAST_FAILED_FILENAME);
    }

    private function parseRepeat(InputInterface $input): int
    {
        $raw = $input->getOption(self::OPT_REPEAT);
        $value = is_numeric($raw) ? (int) $raw : 1;
        if ($value < 1) {
            throw new InvalidArgumentException(sprintf(
                '--repeat must be a positive integer, got %s.',
                is_string($raw) ? $raw : (string) $value,
            ));
        }

        return $value;
    }

    private function parseSeed(InputInterface $input): ?int
    {
        $raw = $input->getOption(self::OPT_SEED);
        if ($raw === null || $raw === '') {
            return null;
        }

        if (!is_numeric($raw)) {
            throw new InvalidArgumentException(sprintf('--seed must be an integer, got %s.', (string) $raw));
        }

        return (int) $raw;
    }

    /**
     * @return list<string>
     */
    private function readLastFailed(): array
    {
        $path = $this->lastFailedFilePath();
        if ($path === null || !is_file($path)) {
            return [];
        }

        $contents = @file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return [];
        }

        $entries = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $entries[] = $line;
            }
        }

        return $entries;
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
