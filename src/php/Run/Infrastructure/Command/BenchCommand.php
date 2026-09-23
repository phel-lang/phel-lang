<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Override;
use Phel\Run\Application\Bench\AbBenchException;
use Phel\Run\Application\Bench\AbBenchRunner;
use Phel\Run\Domain\Bench\AbBenchOptions;
use Phel\Run\Domain\QuotedNamespaceList;
use Phel\Run\RunFacade;
use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\NamespaceInformation;
use Phel\Shared\Process\PhelBinaryLocator;
use Phel\Shared\ScalarCoercion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function ctype_digit;
use function defined;
use function function_exists;
use function getcwd;
use function implode;
use function is_numeric;
use function is_string;
use function json_encode;
use function ob_end_clean;
use function ob_start;
use function sprintf;
use function str_contains;

/**
 * Runs the `defbench` benchmarks of the given paths.
 *
 * The measurement itself lives in `phel.bench`, in Phel, for the same reason
 * the test runner lives in `phel.test`: a benchmark body is Phel, and timing it
 * from PHP would put a language boundary inside the measured region.
 *
 * @method RunFacade getFacade()
 *
 * @internal
 */
#[ServiceMap(method: 'getFacade', className: RunFacade::class)]
final class BenchCommand extends Command
{
    use ServiceResolverAwareTrait;

    public const string COMMAND_NAME = 'bench';

    private const string ARG_PATHS = 'paths';

    private const string OPT_FILTER = 'filter';

    private const string OPT_REVS = 'revs';

    private const string OPT_ITERATIONS = 'iterations';

    private const string OPT_WARMUP = 'warmup';

    private const string OPT_STORE = 'store';

    private const string OPT_REF = 'ref';

    private const string OPT_TOLERANCE = 'tolerance';

    private const string OPT_AB = 'ab';

    private const string OPT_PAIRS = 'pairs';

    private const int DEFAULT_PAIRS = 5;

    private ?AbBenchRunner $abRunner = null;

    /**
     * @return list<int>
     */
    #[Override]
    public function getSubscribedSignals(): array
    {
        return defined('SIGINT') && function_exists('pcntl_signal') ? [SIGINT, SIGTERM] : [];
    }

    /**
     * Without the pcntl extension no handler runs, and an interrupted A/B
     * run leaves its worktree behind.
     */
    #[Override]
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int
    {
        $this->abRunner?->abort();

        return 128 + $signal;
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Runs the benchmarks of the given paths. If no paths are provided all benchmarks in the "tests" directory are executed')
            ->setHelp(<<<'HELP'
                Runs every function defined with <info>phel.bench/defbench</info> and prints one row per
                benchmark: revs, iterations, mean and relative standard deviation.

                  <info>phel bench</info>                                every benchmark under the test dirs
                  <info>phel bench src/app/bench.phel</info>             one file
                  <info>phel bench --filter=sum</info>                   only names containing "sum"
                  <info>phel bench --revs=10000</info>                   override what the benchmarks ask for

                Absolute durations do not travel between machines, so compare two runs on the
                same one. Store a baseline, then measure against it:

                  <info>phel bench --store=.phel/bench-baseline.json</info>
                  <info>phel bench --ref=.phel/bench-baseline.json --tolerance=10</info>

                With <info>--tolerance</info> the command exits non-zero when a benchmark is slower than its
                baseline by more than that percentage. A benchmark missing from the baseline
                reports "new" and can never fail the run.

                A baseline stored ten minutes ago was measured on a colder machine. To compare
                against a git ref instead, run both sides interleaved, A then B, once per pair:

                  <info>phel bench --ab=main --pairs=5 --filter=step</info>

                Side A runs from a temporary git worktree of the ref, which is removed afterwards;
                side B is the working tree. Each row reports the mean of the per-pair deltas and
                how many pairs agree on the sign, and says "noise" when they do not all agree.
                With <info>--tolerance</info> the command exits non-zero only when a benchmark is slower
                by more than that percentage in every pair.
                HELP)
            ->addArgument(self::ARG_PATHS, InputArgument::IS_ARRAY, 'The file paths that you want to benchmark')
            ->addOption(self::OPT_FILTER, 'f', InputOption::VALUE_REQUIRED, 'Only run benchmarks whose name contains this substring')
            ->addOption(self::OPT_REVS, null, InputOption::VALUE_REQUIRED, 'Calls per measured iteration; overrides what the benchmark asks for')
            ->addOption(self::OPT_ITERATIONS, null, InputOption::VALUE_REQUIRED, 'Measured iterations per benchmark; overrides what the benchmark asks for')
            ->addOption(self::OPT_WARMUP, null, InputOption::VALUE_REQUIRED, 'Unmeasured iterations run before measuring')
            ->addOption(self::OPT_STORE, null, InputOption::VALUE_REQUIRED, 'Write the results to this file as a baseline')
            ->addOption(self::OPT_REF, null, InputOption::VALUE_REQUIRED, 'Compare the results against the baseline stored in this file')
            ->addOption(self::OPT_TOLERANCE, null, InputOption::VALUE_REQUIRED, 'Fail when a benchmark is slower than its baseline by more than this percentage')
            ->addOption(self::OPT_AB, null, InputOption::VALUE_REQUIRED, 'Compare the working tree against this git ref, interleaved, in a temporary worktree')
            ->addOption(self::OPT_PAIRS, null, InputOption::VALUE_REQUIRED, 'A/B pairs to run with --ab', (string) self::DEFAULT_PAIRS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption(self::OPT_AB) !== null || $input->getParameterOption('--' . self::OPT_PAIRS, null, true) !== null) {
            return $this->executeAb($input, $output);
        }

        try {
            /** @var list<string> $paths */
            $paths = (array) $input->getArgument(self::ARG_PATHS);
            $namespaces = $this->loadNamespaces($this->getFacade()->getDependenciesFromPaths($paths));

            if ($namespaces === []) {
                $output->writeln('<error>No benchmarks found in the given paths.</error>');

                return self::FAILURE;
            }

            $result = $this->getFacade()->eval(
                $this->generatePhelCode($input, $namespaces),
                new CompileOptions()->setIsEnabledSourceMaps(false),
            );

            return $result === false ? self::FAILURE : self::SUCCESS;
        } catch (CompilerException $e) {
            $this->getFacade()->writeLocatedException($output, $e);
        } catch (Throwable $e) {
            $this->getFacade()->writeStackTrace($output, $e);
        }

        return self::FAILURE;
    }

    private function executeAb(InputInterface $input, OutputInterface $output): int
    {
        $options = $this->abOptions($input);
        if (is_string($options)) {
            $output->writeln('<error>' . $options . '</error>');

            return self::INVALID;
        }

        $this->abRunner = $this->getFacade()->createAbBenchRunner();

        try {
            $withinTolerance = $this->abRunner->run($options, getcwd() ?: '.', PhelBinaryLocator::locate(), $output);

            return $withinTolerance ? self::SUCCESS : self::FAILURE;
        } catch (AbBenchException $abBenchException) {
            $output->writeln('<error>' . $abBenchException->getMessage() . '</error>');

            return self::FAILURE;
        } finally {
            $this->abRunner = null;
        }
    }

    /**
     * @return AbBenchOptions|string the options, or why they are not valid
     */
    private function abOptions(InputInterface $input): AbBenchOptions|string
    {
        $ref = $input->getOption(self::OPT_AB);
        if (!is_string($ref) || $ref === '') {
            return '--pairs only applies together with --ab=<git-ref>.';
        }

        foreach ([self::OPT_STORE, self::OPT_REF] as $option) {
            if ($input->getOption($option) !== null) {
                return sprintf('--ab cannot be combined with --%s: an A/B run is not stored or compared against a baseline.', $option);
            }
        }

        $pairs = ScalarCoercion::toString($input->getOption(self::OPT_PAIRS));
        if (!ctype_digit($pairs) || (int) $pairs < 1) {
            return sprintf('--pairs must be a whole number of at least 1, got "%s".', $pairs);
        }

        $tolerance = $input->getOption(self::OPT_TOLERANCE);
        if ($tolerance !== null && !is_numeric($tolerance)) {
            return sprintf('--tolerance must be a number, got "%s".', ScalarCoercion::toString($tolerance));
        }

        $benchArguments = [];
        foreach ([self::OPT_FILTER, self::OPT_REVS, self::OPT_ITERATIONS, self::OPT_WARMUP] as $option) {
            $value = $input->getOption($option);
            if ($value !== null) {
                $benchArguments[] = sprintf('--%s=%s', $option, ScalarCoercion::toString($value));
            }
        }

        /** @var list<string> $paths */
        $paths = (array) $input->getArgument(self::ARG_PATHS);

        return new AbBenchOptions(
            $ref,
            (int) $pairs,
            $paths,
            $benchArguments,
            $tolerance === null ? null : (float) $tolerance,
        );
    }

    /**
     * @param list<NamespaceInformation> $namespacesInformation
     *
     * @return list<NamespaceInformation>
     */
    private function loadNamespaces(array $namespacesInformation): array
    {
        $loaded = [];
        foreach ($namespacesInformation as $info) {
            // PHPUnit-only fixtures, exactly as the test command skips them:
            // evaluating them as Phel namespaces is wrong.
            if (str_contains($info->getFile(), 'tests/php/')) {
                continue;
            }

            ob_start();

            try {
                $this->getFacade()->evalFile($info);
            } finally {
                ob_end_clean();
            }

            $loaded[] = $info;
        }

        return $loaded;
    }

    /**
     * @param list<NamespaceInformation> $namespaces
     */
    private function generatePhelCode(InputInterface $input, array $namespaces): string
    {
        return sprintf(
            '(phel.bench/run-benchmarks %s %s)',
            $this->optionsAsPhelHashMap($input),
            QuotedNamespaceList::of($namespaces),
        );
    }

    private function optionsAsPhelHashMap(InputInterface $input): string
    {
        $entries = [];

        foreach ([self::OPT_REVS, self::OPT_ITERATIONS, self::OPT_WARMUP, self::OPT_TOLERANCE] as $option) {
            $value = $input->getOption($option);
            if ($value !== null) {
                $entries[] = sprintf(':%s %d', $option, ScalarCoercion::toInt($value));
            }
        }

        foreach ([self::OPT_FILTER, self::OPT_STORE, self::OPT_REF] as $option) {
            $value = $input->getOption($option);
            if (is_string($value)) {
                // Encoded rather than interpolated: a Windows path carries
                // backslashes, which are escapes inside a Phel string literal.
                // Slashes stay unescaped: `json_encode` would turn every `/`
                // of a POSIX path into `\/`, which Phel's reader keeps
                // verbatim, and the run would write to a path that cannot
                // exist.
                $entries[] = sprintf(
                    ':%s %s',
                    $option,
                    json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                );
            }
        }

        return '{' . implode(' ', $entries) . '}';
    }
}
