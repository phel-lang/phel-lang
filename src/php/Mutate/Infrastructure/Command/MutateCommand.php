<?php

declare(strict_types=1);

namespace Phel\Mutate\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use InvalidArgumentException;
use Phel\Mutate\Domain\Exception\BaselineFailedException;
use Phel\Mutate\Domain\Exception\WorkerFailedException;
use Phel\Mutate\Domain\MutantResult;
use Phel\Mutate\Domain\MutantVerdict;
use Phel\Mutate\Domain\MutateOptions;
use Phel\Mutate\Domain\MutationReport;
use Phel\Mutate\MutateConfig;
use Phel\Mutate\MutateFacade;
use Phel\Mutate\MutateFactory;
use Phel\Shared\Process\GitUnavailableException;
use Phel\Shared\ScalarCoercion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function explode;
use function file_put_contents;
use function is_numeric;
use function is_string;
use function microtime;
use function sprintf;
use function strtolower;
use function trim;

/**
 * `./bin/phel mutate [paths...]` — mutation testing for Phel code: every
 * `defn` under the given paths (default: the project source dirs) is
 * mutated one small change at a time, the test suite runs against each
 * mutant, and the survivors are listed. Exits with:
 *   0 — the run completed (and, with --min-msi, the score reached it)
 *   1 — the baseline suite is red, the worker failed, or the score is below --min-msi
 *   2 — invocation error (unknown mutator, unreadable option).
 *
 * @method MutateFacade  getFacade()
 * @method MutateFactory getFactory()
 * @method MutateConfig  getConfig()
 *
 * @internal
 */
#[ServiceMap(method: 'getFacade', className: MutateFacade::class)]
#[ServiceMap(method: 'getFactory', className: MutateFactory::class)]
#[ServiceMap(method: 'getConfig', className: MutateConfig::class)]
final class MutateCommand extends Command
{
    use ServiceResolverAwareTrait;

    public const int EXIT_INVOCATION_ERROR = 2;

    public const string COMMAND_NAME = 'mutate';

    private const string ARG_PATHS = 'paths';

    private const string OPT_TESTS = 'tests';

    private const string OPT_ONLY = 'only';

    private const string OPT_MIN_MSI = 'min-msi';

    private const string OPT_MIN_COVERED_MSI = 'min-covered-msi';

    private const string OPT_REPORTER = 'reporter';

    private const string OPT_OUTPUT = 'output';

    private const string OPT_TIMEOUT_FACTOR = 'timeout-factor';

    private const string OPT_PARALLEL = 'parallel';

    private const string OPT_CHANGED = 'changed';

    private const string REPORTER_TEXT = 'text';

    private const string REPORTER_JSON = 'json';

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Mutation testing: mutate every defn under the given paths and report the mutants the test suite does not catch.')
            ->addArgument(self::ARG_PATHS, InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Phel files or directories to mutate. Default: the project source dirs.')
            ->addOption(self::OPT_TESTS, null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Test files or directories to run against every mutant. Repeatable. Default: the project test dirs.')
            ->addOption(self::OPT_ONLY, null, InputOption::VALUE_REQUIRED, 'Comma-separated mutator ids to use, e.g. "arith,compare". Default: all.')
            ->addOption(self::OPT_MIN_MSI, null, InputOption::VALUE_REQUIRED, 'Fail (exit 1) when the mutation score indicator is below this percentage.')
            ->addOption(self::OPT_MIN_COVERED_MSI, null, InputOption::VALUE_REQUIRED, 'Fail (exit 1) when the covered MSI (over the mutants some test reaches) is below this percentage.')
            ->addOption(self::OPT_REPORTER, null, InputOption::VALUE_REQUIRED, 'Report format: "text" (default) or "json".', self::REPORTER_TEXT, [self::REPORTER_TEXT, self::REPORTER_JSON])
            ->addOption(self::OPT_OUTPUT, 'o', InputOption::VALUE_REQUIRED, 'Write the report to a file instead of stdout.')
            ->addOption(self::OPT_TIMEOUT_FACTOR, null, InputOption::VALUE_REQUIRED, 'A mutant whose test run takes longer than this many times the baseline is a timeout (counts as killed). Never below 1 second.', '3')
            ->addOption(self::OPT_PARALLEL, null, InputOption::VALUE_REQUIRED, 'Worker subprocesses to run mutants on: an integer, or "auto" (CPU count, capped at 8).', '1', ['auto'])
            ->addOption(self::OPT_CHANGED, null, InputOption::VALUE_OPTIONAL, 'Mutate only the source files git reports as changed: uncommitted changes (or the changes since the merge base with the default branch when clean), or `git diff <ref>` with a value.', false)
            ->setHelp(<<<'HELP'
Runs the unmutated suite once (it must be green), then evaluates every mutant in a
worker subprocess: the mutated definition replaces the original, the tests run, and
the original is restored. A mutant the tests fail on is killed; one they pass is a
survivor and is listed with its location, mutator and change.

Examples:
  phel mutate                              Mutate the project sources, run the project tests
  phel mutate src/app/calc.phel            One file
  phel mutate --only=arith,compare         Two mutators
  phel mutate --min-msi=80 --reporter=json -o var/mutation.json
  phel mutate --parallel=auto              One worker per CPU (capped at 8)
  phel mutate --changed                    Only the source files with uncommitted changes
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $options = $this->parseOptions($input);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $output->writeln('<error>' . $invalidArgumentException->getMessage() . '</error>');

            return self::EXIT_INVOCATION_ERROR;
        }

        try {
            $plan = $this->getFacade()->plan($options);
            $mutants = $this->getFacade()->generate($plan, $options);
        } catch (InvalidArgumentException|GitUnavailableException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return self::EXIT_INVOCATION_ERROR;
        }

        $output->writeln(sprintf(
            'Mutating %d file(s), %d mutant(s), testing with %d namespace(s) on %d worker(s)...',
            count($plan->sourceFiles),
            count($mutants),
            count($plan->testNamespaces),
            $options->workers,
        ));

        try {
            $startedAt = microtime(true);
            $this->getFacade()->warm($plan);
            $output->writeln(sprintf('Loaded %d file(s) in %.1fs; starting %d worker(s)...', count($plan->loadOrder), microtime(true) - $startedAt, $options->workers));

            $report = $this->getFacade()->run($plan, $options, $mutants, static function (MutantResult $result) use ($output): void {
                $output->write(self::marker($result));
            });
        } catch (BaselineFailedException|WorkerFailedException $exception) {
            $output->writeln('');
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return self::FAILURE;
        }

        $output->writeln('');
        $output->writeln('');
        $this->writeReport($output, $report, $input);

        if (!$report->meetsMinimum($options->minMsi, $options->minCoveredMsi)) {
            $output->writeln(sprintf(
                '<error>MSI %.1f%% (covered %.1f%%) is below the required %s.</error>',
                $report->msi(),
                $report->coveredMsi(),
                implode(' / ', array_filter([
                    $options->minMsi === null ? null : sprintf('MSI %.1f%%', $options->minMsi),
                    $options->minCoveredMsi === null ? null : sprintf('covered MSI %.1f%%', $options->minCoveredMsi),
                ])),
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function parseOptions(InputInterface $input): MutateOptions
    {
        $only = $input->getOption(self::OPT_ONLY);
        $mutators = is_string($only) && trim($only) !== ''
            ? array_values(array_filter(array_map(trim(...), explode(',', $only)), static fn(string $id): bool => $id !== ''))
            : [];

        $minMsi = $input->getOption(self::OPT_MIN_MSI);
        if ($minMsi !== null && !is_numeric($minMsi)) {
            throw new InvalidArgumentException('--min-msi must be a number between 0 and 100.');
        }

        $minCoveredMsi = $input->getOption(self::OPT_MIN_COVERED_MSI);
        if ($minCoveredMsi !== null && !is_numeric($minCoveredMsi)) {
            throw new InvalidArgumentException('--min-covered-msi must be a number between 0 and 100.');
        }

        $factor = $input->getOption(self::OPT_TIMEOUT_FACTOR);
        if (!is_numeric($factor) || (float) $factor <= 0) {
            throw new InvalidArgumentException('--timeout-factor must be a positive number.');
        }

        /** @var list<string> $paths */
        $paths = (array) $input->getArgument(self::ARG_PATHS);
        /** @var list<string> $tests */
        $tests = (array) $input->getOption(self::OPT_TESTS);
        $changed = $input->getOption(self::OPT_CHANGED);

        return new MutateOptions(
            $paths,
            $tests,
            $mutators,
            (float) $factor,
            $minMsi === null ? null : (float) $minMsi,
            $minCoveredMsi === null ? null : (float) $minCoveredMsi,
            $this->parseWorkers($input),
            $changed !== false,
            is_string($changed) && $changed !== '' ? $changed : null,
        );
    }

    private function parseWorkers(InputInterface $input): int
    {
        $raw = $input->getOption(self::OPT_PARALLEL);
        if (is_string($raw) && strtolower($raw) === 'auto') {
            return $this->getFacade()->detectWorkerCount();
        }

        if (!is_numeric($raw) || (int) $raw < 1) {
            throw new InvalidArgumentException('--parallel must be an integer >= 1 or "auto".');
        }

        return (int) $raw;
    }

    private function writeReport(OutputInterface $output, MutationReport $report, InputInterface $input): void
    {
        $reporter = ScalarCoercion::toString($input->getOption(self::OPT_REPORTER), self::REPORTER_TEXT);
        $content = $reporter === self::REPORTER_JSON ? $report->toJson() : $report->toText();

        $path = $input->getOption(self::OPT_OUTPUT);
        if (is_string($path) && $path !== '') {
            if (@file_put_contents($path, $content) === false) {
                $output->writeln(sprintf('<error>Cannot write the mutation report to %s</error>', $path));
                return;
            }

            $output->writeln(sprintf('Mutation report written to %s', $path));
            if ($reporter === self::REPORTER_JSON) {
                $output->write($report->toText());
            }

            return;
        }

        $output->write($content);
    }

    private static function marker(MutantResult $result): string
    {
        return match ($result->verdict) {
            MutantVerdict::Killed => '.',
            MutantVerdict::Survived => 'S',
            MutantVerdict::Error => 'E',
            MutantVerdict::Timeout => 'T',
            MutantVerdict::NotCovered => 'N',
        };
    }
}
