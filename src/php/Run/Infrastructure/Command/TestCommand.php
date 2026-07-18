<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use ArrayAccess;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Run\Application\Test\Coverage\CoverageDriver;
use Phel\Run\Application\Test\Coverage\CoverageReport;
use Phel\Run\Application\Test\Coverage\HtmlCoverageRenderer;
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
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_string;
use function mkdir;
use function sprintf;
use function strpos;
use function strtolower;
use function substr;
use function time;

/**
 * @method RunFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: RunFacade::class)]
final class TestCommand extends Command
{
    use ServiceResolverAwareTrait;

    public const string COMMAND_NAME = 'test';

    private const string OPT_COVERAGE = 'coverage';

    private const string OPT_COVERAGE_OUTPUT = 'coverage-output';

    private const string DEFAULT_HTML_COVERAGE_DIR = 'var/coverage';

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setAliases(['t'])
            ->setDescription(
                'Tests the given files. If no filenames are provided all tests in the "tests" directory are executed',
            )
            ->setHelp(<<<'HELP'
Runs the test suite (all tests by default, or the files/namespaces you pass).

<info>Examples:</info>
  <comment>phel test</comment>                     Run every test
  <comment>phel test --filter=greet --parallel=auto</comment>   Filter by name, run in parallel
HELP)
            ->addArgument(
                TestCommandOptionParser::ARG_PATHS,
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The file paths that you want to test.',
                [],
                fn(): array => $this->getFacade()->getAllNamespaces(),
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
                ['default', 'testdox', 'dot', 'tap', 'junit-xml'],
            )->addOption(
                TestCommandOptionParser::OPT_OUTPUT,
                'o',
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
                fn(): array => $this->getFacade()->getAllNamespaces(),
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
                null,
                ['auto', 'max'],
            )->addOption(
                TestCommandOptionParser::OPT_WATCH,
                null,
                InputOption::VALUE_NONE,
                'Re-run the selected tests whenever a .phel file (or phel-config.php) under the project source/test directories changes. Press Ctrl+C to stop.',
            )->addOption(
                self::OPT_COVERAGE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Collect line coverage (via pcov or xdebug) mapped back to .phel sources. Value is the format: "text" (default), "clover", or "html". "html" writes a static report to var/coverage/ (override with "html:<dir>").',
                false,
                ['text', 'clover', 'html'],
            )->addOption(
                self::OPT_COVERAGE_OUTPUT,
                null,
                InputOption::VALUE_REQUIRED,
                'Write the coverage report to a file instead of stdout (use with --coverage=clover for CI). With --coverage=html the value is the report directory.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ((bool) $input->getOption(TestCommandOptionParser::OPT_WATCH)) {
            return $this->runWatchMode($output);
        }

        $optionParser = new TestCommandOptionParser();
        $feedback = TestLoadingFeedback::fromOutput($output);

        try {
            $feedback->discovering();
            /** @var list<string> $paths */
            $paths = (array) $input->getArgument(TestCommandOptionParser::ARG_PATHS);
            $namespacesInformation = $this->getFacade()->getDependenciesFromPaths($paths);
            $failFast = (bool) $input->getOption(TestCommandOptionParser::OPT_FAIL_FAST);
            /** @var list<string> $nsPatterns */
            $nsPatterns = (array) $input->getOption(TestCommandOptionParser::OPT_NS);
            $namespacesInformation = new TestNamespacePruner()->prune($namespacesInformation, $nsPatterns);

            [$filteredNamespaces, $compileErrors] = $this->loadTestNamespaces(
                $namespacesInformation,
                $failFast,
                $feedback,
            );

            $this->reportCompileErrors($output, $compileErrors);

            if ($filteredNamespaces === []) {
                return ($compileErrors === []) ? self::SUCCESS : self::FAILURE;
            }

            $coverageRequested = $input->getOption(self::OPT_COVERAGE) !== false;
            $coverageDriver = null;
            if ($coverageRequested) {
                $coverageDriver = $this->getFacade()->detectCoverageDriver();
                if (!$coverageDriver instanceof CoverageDriver) {
                    $output->writeln(
                        '<error>--coverage requires the pcov or xdebug extension; '
                        . CoverageDriver::unavailabilityReason() . '</error>',
                    );
                    return self::FAILURE;
                }
            }

            $workerCount = $optionParser->decideParallelism(
                $input,
                $output,
                $this->getFacade()->createCpuCountDetector(),
            );
            $options = $optionParser->collectOptions($input);

            // Coverage runs serially: workers are separate processes whose
            // coverage cannot be merged here. Tell the user and fall back.
            if ($coverageDriver instanceof CoverageDriver && $workerCount !== null) {
                $output->writeln('<comment>--coverage is not supported with --parallel; running serially.</comment>');
                $workerCount = null;
            }

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

            $coverageDriver?->start();
            $result = $this->getFacade()->eval($phelCode, $compileOptions);
            if ($coverageDriver instanceof CoverageDriver) {
                $this->renderCoverage(
                    $output,
                    $this->getFacade()->buildCoverageReport($coverageDriver->stop(), $coverageDriver->name()),
                    ScalarCoercion::toString($input->getOption(self::OPT_COVERAGE)),
                    $input->getOption(self::OPT_COVERAGE_OUTPUT),
                );
            }

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
     * Watch mode: re-runs this same invocation (minus --watch) as a subprocess
     * whenever a watched file changes, so each run starts from a clean runtime.
     */
    private function runWatchMode(OutputInterface $output): int
    {
        $command = new WatchRerunCommandBuilder()->build(
            ScalarCoercion::toStringList($_SERVER['argv'] ?? null),
            ScalarCoercion::toString($_SERVER['SCRIPT_FILENAME'] ?? null),
        );
        $runTests = static function () use ($command): int {
            $exitCode = 1;
            passthru($command, $exitCode);

            return $exitCode;
        };

        return $this->getFacade()->runTestWatchLoop($runTests, $output);
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

    private function renderCoverage(
        OutputInterface $output,
        CoverageReport $report,
        string $format,
        mixed $outputPath,
    ): void {
        // "html:<dir>" carries the report directory in the format value itself.
        $colonPos = strpos($format, ':');
        $formatSuffix = $colonPos === false ? '' : substr($format, $colonPos + 1);
        $format = strtolower($colonPos === false ? $format : substr($format, 0, $colonPos));
        $format = $format === '' ? 'text' : $format;

        if ($format === 'html') {
            $directory = $formatSuffix !== ''
                ? $formatSuffix
                : ((is_string($outputPath) && $outputPath !== '') ? $outputPath : self::DEFAULT_HTML_COVERAGE_DIR);
            $this->writeHtmlCoverage($output, $report, $directory);
            return;
        }

        $content = $format === 'clover'
            ? $report->toClover(time())
            : $report->toText();

        if (is_string($outputPath) && $outputPath !== '') {
            if (@file_put_contents($outputPath, $content) === false) {
                $output->writeln(sprintf('<error>Cannot write coverage report to %s</error>', $outputPath));
                return;
            }

            $output->writeln(sprintf('Coverage report written to %s', $outputPath));
            return;
        }

        $output->writeln($content);
    }

    private function writeHtmlCoverage(OutputInterface $output, CoverageReport $report, string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            $output->writeln(sprintf('<error>Cannot create coverage report directory %s</error>', $directory));
            return;
        }

        foreach (new HtmlCoverageRenderer()->render($report) as $pageName => $html) {
            if (@file_put_contents($directory . '/' . $pageName, $html) === false) {
                $output->writeln(sprintf('<error>Cannot write coverage page %s</error>', $directory . '/' . $pageName));
                return;
            }
        }

        $output->writeln(sprintf('HTML coverage report written to %s', $directory . '/index.html'));
    }

    /**
     * Evaluates each discovered namespace, suppressing load-phase output and
     * skipping PHPUnit-only fixtures. Returns the namespaces that compiled
     * cleanly alongside the errors collected from the rest. With $failFast the
     * first failure rethrows after restoring the output buffer and feedback.
     *
     * @param list<NamespaceInformation> $namespacesInformation
     *
     * @return array{0: list<NamespaceInformation>, 1: list<array{0: NamespaceInformation, 1: Throwable}>}
     */
    private function loadTestNamespaces(
        array $namespacesInformation,
        bool $failFast,
        TestLoadingFeedback $feedback,
    ): array {
        $feedback->startLoading(count($namespacesInformation));
        ob_start();
        $filteredNamespaces = [];
        $compileErrors = [];
        foreach ($namespacesInformation as $info) {
            $feedback->advance($info->getNamespace());
            // These fixtures are PHPUnit-only; evaluating them as Phel namespaces is wrong.
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
                    $feedback->finishLoading();
                    throw $e;
                }

                $compileErrors[] = [$info, $e];
            }
        }

        ob_end_clean();
        $feedback->finishLoading();

        return [$filteredNamespaces, $compileErrors];
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
                $this->getFacade()->writeStackTrace($output, $e);
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
