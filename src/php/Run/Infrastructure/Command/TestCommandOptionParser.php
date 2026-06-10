<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use InvalidArgumentException;
use Phel\Lang\ProfilerHookInterface;
use Phel\Lang\Registry;
use Phel\Run\Application\Test\CpuCountDetector;
use Phel\Run\Domain\Test\TestCommandOptions;
use Phel\Shared\PhelProjectDirectory;
use Phel\Shared\ScalarCoercion;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function getcwd;
use function in_array;
use function is_numeric;
use function is_string;
use function sprintf;
use function strtolower;

/**
 * Parses and coerces the `test` command's CLI options into the runtime
 * `$options` array and the parallelism decision. {@see TestCommand} declares
 * the options (using the constants here) and orchestrates the run; all the
 * input reading, validation, and coercion lives in this stateless collaborator.
 */
final readonly class TestCommandOptionParser
{
    public const string ARG_PATHS = 'paths';

    public const string OPT_FILTER = 'filter';

    public const string OPT_TESTDOX = 'testdox';

    public const string OPT_FAIL_FAST = 'fail-fast';

    public const string OPT_STACK_TRACE = 'stack-trace';

    public const string OPT_REPORTER = 'reporter';

    public const string OPT_OUTPUT = 'output';

    public const string OPT_INCLUDE = 'include';

    public const string OPT_EXCLUDE = 'exclude';

    public const string OPT_NS = 'ns';

    public const string OPT_LIST = 'list';

    public const string OPT_LAST_FAILED = 'last-failed';

    public const string OPT_SLOWEST = 'slowest';

    public const string OPT_REPEAT = 'repeat';

    public const string OPT_SEED = 'seed';

    public const string OPT_RANDOM_ORDER = 'random-order';

    public const string OPT_PARALLEL = 'parallel';

    private const string LAST_FAILED_FILENAME = 'last-failed.txt';

    /**
     * @return array<string, mixed>
     */
    public function collectOptions(InputInterface $input): array
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
            TestCommandOptions::SLOWEST => ScalarCoercion::toInt($input->getOption(self::OPT_SLOWEST)),
            TestCommandOptions::REPEAT => $this->parseRepeat($input),
            TestCommandOptions::SEED => $this->parseSeed($input),
            TestCommandOptions::RANDOM_ORDER => (bool) $input->getOption(self::OPT_RANDOM_ORDER),
        ];
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
    public function decideParallelism(InputInterface $input, OutputInterface $output, CpuCountDetector $detector): ?int
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

        if (is_string($raw)) {
            $keyword = strtolower($raw);
            if ($keyword === 'auto') {
                return $detector->detect();
            }

            if ($keyword === 'max') {
                return $detector->detectMax();
            }
        }

        if (!is_numeric($raw)) {
            throw new InvalidArgumentException(sprintf(
                '--parallel must be an integer >= 1, "auto", or "max", got %s.',
                ScalarCoercion::toString($raw),
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
        if (!is_numeric($raw) || (int) $raw < 1) {
            throw new InvalidArgumentException(sprintf(
                '--repeat must be a positive integer, got %s.',
                ScalarCoercion::toString($raw),
            ));
        }

        return (int) $raw;
    }

    private function parseSeed(InputInterface $input): ?int
    {
        $raw = $input->getOption(self::OPT_SEED);
        if ($raw === null || $raw === '') {
            return null;
        }

        if (!is_numeric($raw)) {
            throw new InvalidArgumentException(sprintf('--seed must be an integer, got %s.', ScalarCoercion::toString($raw)));
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
}
