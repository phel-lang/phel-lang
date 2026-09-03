<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Run\Domain\QuotedNamespaceList;
use Phel\Run\RunFacade;
use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\NamespaceInformation;
use Phel\Shared\ScalarCoercion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function implode;
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
                HELP)
            ->addArgument(self::ARG_PATHS, InputArgument::IS_ARRAY, 'The file paths that you want to benchmark')
            ->addOption(self::OPT_FILTER, 'f', InputOption::VALUE_REQUIRED, 'Only run benchmarks whose name contains this substring')
            ->addOption(self::OPT_REVS, null, InputOption::VALUE_REQUIRED, 'Calls per measured iteration; overrides what the benchmark asks for')
            ->addOption(self::OPT_ITERATIONS, null, InputOption::VALUE_REQUIRED, 'Measured iterations per benchmark; overrides what the benchmark asks for')
            ->addOption(self::OPT_WARMUP, null, InputOption::VALUE_REQUIRED, 'Unmeasured iterations run before measuring')
            ->addOption(self::OPT_STORE, null, InputOption::VALUE_REQUIRED, 'Write the results to this file as a baseline')
            ->addOption(self::OPT_REF, null, InputOption::VALUE_REQUIRED, 'Compare the results against the baseline stored in this file')
            ->addOption(self::OPT_TOLERANCE, null, InputOption::VALUE_REQUIRED, 'Fail when a benchmark is slower than its baseline by more than this percentage');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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
