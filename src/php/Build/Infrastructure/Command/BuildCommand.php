<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Build\BuildFacade;
use Phel\Build\Domain\Compile\BuildOptions;
use Phel\Build\Domain\Compile\BuildReport;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Compile\PhaseTimingReport;
use Phel\Build\Infrastructure\Timing\PhaseTimingProfilerHook;
use Phel\Lang\Registry;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\ResourceUsageFormatter;
use Phel\Shared\ScalarCoercion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_filter;
use function count;
use function hrtime;
use function sprintf;

#[ServiceMap(method: 'getFacade', className: BuildFacade::class)]
final class BuildCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const string OPTION_CACHE = 'cache';

    private const string OPTION_SOURCE_MAP = 'source-map';

    private const string OPTION_OPTIMIZATION_LEVEL = 'optimization-level';

    private const string OPTION_REPORT = 'report';

    private const string OPTION_TIMING = 'timing';

    protected function configure(): void
    {
        $this->setName('build')
            ->setAliases(['b'])
            ->setDescription('Build the current project')
            ->setHelp(<<<'HELP'
Compiles every project namespace to PHP in the output directory.

<info>Examples:</info>
  <comment>phel build</comment>                  Incremental build using the cache
  <comment>phel build --no-cache -O2</comment>     Clean, fully optimized build
HELP)
            ->addOption(self::OPTION_CACHE, null, InputOption::VALUE_NEGATABLE, 'Enable cache', true)
            ->addOption(self::OPTION_SOURCE_MAP, null, InputOption::VALUE_NEGATABLE, 'Enable source maps', true)
            ->addOption(
                self::OPTION_OPTIMIZATION_LEVEL,
                'O',
                InputOption::VALUE_REQUIRED,
                'Override the configured optimization level (0 = off, 2 = inline + tail-call rewrite)',
            )
            ->addOption(
                self::OPTION_REPORT,
                null,
                InputOption::VALUE_NONE,
                'Print a build report: namespace count, per-namespace compiled size, total size, and build time',
            )
            ->addOption(
                self::OPTION_TIMING,
                null,
                InputOption::VALUE_NONE,
                'Print per-phase compile timing (lex/parse/read/analyze/emit) aggregated across compiled namespaces; pair with --no-cache for a full, comparable measurement',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $buildOptions = $this->getBuildOptions($input);
        $report = (bool) $input->getOption(self::OPTION_REPORT);

        $timingHook = (bool) $input->getOption(self::OPTION_TIMING)
            ? new PhaseTimingProfilerHook()
            : null;
        if ($timingHook instanceof PhaseTimingProfilerHook) {
            Registry::$profilerHook = $timingHook;
        }

        try {
            $startedAt = hrtime(true);
            $compiledProject = $this->getFacade()->compileProject($buildOptions);
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;

            if ($report) {
                $this->printReport($output, BuildReport::fromCompiledFiles($compiledProject, $durationMs));
            } else {
                $this->printOutput($output, $compiledProject);
            }

            if ($timingHook instanceof PhaseTimingProfilerHook) {
                $this->printPhaseTiming($output, $timingHook->report());
            }
        } catch (CompilerException $e) {
            $this->getFacade()->writeLocatedException($output, $e);
        } catch (Throwable $e) {
            $this->getFacade()->writeStackTrace($output, $e);
        } finally {
            Registry::$profilerHook = null;
        }

        $output->writeln(new ResourceUsageFormatter()->resourceUsageSinceStartOfRequest());

        return self::SUCCESS;
    }

    private function printPhaseTiming(OutputInterface $output, PhaseTimingReport $timing): void
    {
        $output->writeln('');
        $output->writeln('Compile-phase timing');
        $output->writeln('====================');

        if ($timing->isEmpty()) {
            $output->writeln('  No phases recorded — every namespace was served from cache. Re-run with --no-cache.');
            return;
        }

        foreach ($timing->phases() as $row) {
            $output->writeln(sprintf('  %-9s %10.2f ms  %5.1f%%', $row['phase'], $row['ms'], $row['share']));
        }

        $output->writeln(sprintf('  %-9s %10.2f ms', 'total', $timing->totalMs()));
        $output->writeln(sprintf(
            '  (%d namespace%s compiled)',
            $timing->sourceCount(),
            $timing->sourceCount() === 1 ? '' : 's',
        ));
    }

    private function printReport(OutputInterface $output, BuildReport $report): void
    {
        if ($report->namespaceCount() === 0) {
            $output->writeln('No Phel namespaces found to build.');
            return;
        }

        $output->writeln('Build report');
        $output->writeln('============');

        foreach ($report->entries() as $entry) {
            $output->writeln(sprintf(
                '  %-40s %9s  %s',
                $entry->namespace,
                $this->formatBytes($entry->bytes),
                $entry->cached ? '(cached)' : '(fresh)',
            ));
        }

        $output->writeln('');
        $output->writeln(sprintf(
            'Namespaces: %d (%d fresh, %d cached) | Total: %s | Time: %.1f ms | Output: %s',
            $report->namespaceCount(),
            $report->freshCount(),
            $report->cachedCount(),
            $this->formatBytes($report->totalBytes()),
            $report->durationMs(),
            $this->getFacade()->getOutputDirectory(),
        ));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return sprintf('%.2f MB', $bytes / 1_048_576);
        }

        if ($bytes >= 1024) {
            return sprintf('%.2f KB', $bytes / 1024);
        }

        return $bytes . ' B';
    }

    private function getBuildOptions(InputInterface $input): BuildOptions
    {
        $rawLevel = $input->getOption(self::OPTION_OPTIMIZATION_LEVEL);

        return new BuildOptions(
            $input->getOption(self::OPTION_CACHE) === true,
            $input->getOption(self::OPTION_SOURCE_MAP) === true,
            $rawLevel === null ? null : max(0, ScalarCoercion::toInt($rawLevel)),
        );
    }

    /**
     * @param list<CompiledFile> $compiledProject
     */
    private function printOutput(OutputInterface $output, array $compiledProject): void
    {
        $fresh = array_filter($compiledProject, static fn(CompiledFile $f): bool => !$f->isCached());
        $cachedCount = count($compiledProject) - count($fresh);

        $index = 0;
        foreach ($fresh as $compiledFile) {
            $output->writeln(
                sprintf(
                    "#%d | Namespace: %s\nSource: %s\nTarget: %s\n",
                    $index,
                    $compiledFile->getNamespace(),
                    $compiledFile->getSourceFile(),
                    $compiledFile->getTargetFile(),
                ),
            );
            ++$index;
        }

        $this->printSummary($output, count($fresh), $cachedCount);
    }

    private function printSummary(OutputInterface $output, int $freshCount, int $cachedCount): void
    {
        $total = $freshCount + $cachedCount;
        if ($total === 0) {
            $output->writeln('No Phel namespaces found to build.');
            return;
        }

        $outputDir = $this->getFacade()->getOutputDirectory();

        if ($freshCount === 0) {
            $output->writeln(sprintf(
                "No changes detected. %d file%s reused from cache.\nCompiled output: %s",
                $cachedCount,
                $cachedCount === 1 ? '' : 's',
                $outputDir,
            ));
            return;
        }

        $output->writeln(sprintf(
            "Compiled %d file%s (%d reused from cache).\nOutput directory: %s",
            $freshCount,
            $freshCount === 1 ? '' : 's',
            $cachedCount,
            $outputDir,
        ));
    }
}
