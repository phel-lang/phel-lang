<?php

declare(strict_types=1);

namespace Phel\Run\Application\Bench;

use Phel\Run\Domain\Bench\AbBenchOptions;
use Phel\Run\Domain\Bench\AbComparison;
use Phel\Run\Domain\Bench\AbReport;
use Phel\Shared\Performance\OpcacheReexec;
use Symfony\Component\Console\Output\OutputInterface;

use function file_get_contents;
use function getenv;
use function is_array;
use function is_file;
use function is_numeric;
use function is_string;
use function json_decode;
use function mkdir;
use function realpath;
use function sprintf;
use function substr;
use function trim;

/**
 * `phel bench --ab=<ref>`: runs the benchmarks of `<ref>` (side A, from a
 * temporary worktree) and of the working tree (side B) interleaved, A then B,
 * once per pair, each side as its own `phel bench` process so both pay the
 * same start-up and neither shares the other's compiled cache.
 *
 * @internal
 */
final class AbBenchRunner
{
    private ?TemporaryWorktree $worktree = null;

    public function __construct(
        private readonly ChildProcess $process,
        private readonly WorktreeVendor $vendor,
        private readonly AbReport $report,
    ) {}

    /**
     * @param string $binary the `phel` entry point of this process
     *
     * @throws AbBenchException
     *
     * @return bool false when a benchmark is slower than side A by more than the tolerance in every pair
     */
    public function run(AbBenchOptions $options, string $projectDir, string $binary, OutputInterface $output): bool
    {
        $projectDir = realpath($projectDir) ?: $projectDir;
        $binary = realpath($binary) ?: $binary;
        $worktree = TemporaryWorktree::forRef($this->process, $projectDir, $options->ref);
        $this->worktree = $worktree;

        try {
            $worktree->add($options->ref);
            $output->writeln(sprintf(
                'A: %s (%s), in %s',
                $options->ref,
                substr($worktree->commit, 0, 9),
                $worktree->treePath(),
            ));
            $output->writeln('B: the working tree');

            $this->vendor->prepare($projectDir, $worktree, $options->ref, $output);
            mkdir($worktree->scratchPath('runs'));

            $sideA = $this->command($worktree->counterpartOf($binary) ?? $binary, $this->pathsForSideA($options, $projectDir, $worktree, $output), $options);
            $sideB = $this->command($binary, $this->absolutePaths($options->paths, $projectDir), $options);

            $comparison = new AbComparison();
            for ($pair = 1; $pair <= $options->pairs; ++$pair) {
                $output->writeln(sprintf('pair %d/%d', $pair, $options->pairs));
                $comparison->addPair(
                    $this->runSide('A', $pair, $sideA, $worktree->treePath(), $worktree),
                    $this->runSide('B', $pair, $sideB, $projectDir, $worktree),
                );
            }

            $output->writeln('');
            if ($comparison->isEmpty()) {
                $output->writeln('No benchmarks found.');

                return true;
            }

            foreach ($this->report->render($comparison) as $line) {
                $output->writeln($line);
            }

            return $this->verdict($comparison, $options, $output);
        } finally {
            $this->worktree = null;
            $worktree->remove();
        }
    }

    /**
     * Stops the running side and removes the worktree. Meant for a signal
     * handler: the process exits right after, so no `finally` runs.
     */
    public function abort(): void
    {
        $this->process->abort();
        $this->worktree?->remove();
    }

    private function verdict(AbComparison $comparison, AbBenchOptions $options, OutputInterface $output): bool
    {
        if ($options->tolerance === null) {
            return true;
        }

        $regressions = $comparison->regressions($options->tolerance);
        if ($regressions === []) {
            return true;
        }

        $output->writeln('');
        $output->writeln(sprintf('Slower than %s by more than %s%% in every pair:', $options->ref, $options->tolerance));
        foreach ($regressions as $row) {
            $output->writeln(sprintf('  %s %+.2f%%', $row->name, $row->meanDeltaPercent()));
        }

        return false;
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function command(string $binary, array $paths, AbBenchOptions $options): array
    {
        return [PHP_BINARY, $binary, 'bench', ...$paths, ...$options->benchArguments];
    }

    /**
     * A path the ref does not have runs from the working tree, so a benchmark
     * added together with the change it measures still gets compared.
     *
     * @return list<string>
     */
    private function pathsForSideA(AbBenchOptions $options, string $projectDir, TemporaryWorktree $worktree, OutputInterface $output): array
    {
        $paths = [];
        foreach ($this->absolutePaths($options->paths, $projectDir) as $index => $path) {
            $counterpart = $worktree->counterpartOf($path);
            if ($counterpart === null) {
                $output->writeln(sprintf('A runs %s from the working tree: it does not exist at %s.', $options->paths[$index], $options->ref));
            }

            $paths[] = $counterpart ?? $path;
        }

        return $paths;
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function absolutePaths(array $paths, string $projectDir): array
    {
        $absolute = [];
        foreach ($paths as $path) {
            $candidate = preg_match('#^([/\\\\]|[A-Za-z]:)#', $path) === 1 ? $path : $projectDir . '/' . $path;
            $absolute[] = realpath($candidate) ?: $candidate;
        }

        return $absolute;
    }

    /**
     * @param list<string> $command
     *
     * @return array<string, float> mean nanoseconds per benchmark
     */
    private function runSide(string $side, int $pair, array $command, string $cwd, TemporaryWorktree $worktree): array
    {
        $results = $worktree->scratchPath(sprintf('runs/%s-%d.json', $side, $pair));
        $log = $worktree->scratchPath(sprintf('runs/%s-%d.log', $side, $pair));

        $exitCode = $this->process->runLogged([...$command, '--store=' . $results], $cwd, $this->childEnvironment(), $log);
        if ($exitCode !== 0) {
            throw new AbBenchException(sprintf(
                "Side %s failed in pair %d with exit code %d:\n%s",
                $side,
                $pair,
                $exitCode,
                trim((string) @file_get_contents($log)),
            ));
        }

        return $this->readResults($results);
    }

    /**
     * @return array<string, float>
     */
    private function readResults(string $file): array
    {
        // `phel bench` writes nothing when its filter matched no benchmark.
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        $results = [];
        if (is_array($decoded)) {
            foreach ($decoded as $name => $mean) {
                if (is_string($name) && is_numeric($mean)) {
                    $results[$name] = (float) $mean;
                }
            }
        }

        return $results;
    }

    /**
     * This process's environment without the OPcache re-exec breadcrumb, so
     * each side re-execs with the OPcache file cache of its own tree, as a
     * standalone `phel bench` would.
     *
     * @return array<string, string>
     */
    private function childEnvironment(): array
    {
        $env = getenv();
        unset($env[OpcacheReexec::REEXEC_DONE_ENV]);

        return $env;
    }
}
