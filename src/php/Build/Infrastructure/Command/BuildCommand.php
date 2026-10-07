<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Build\BuildConfig;
use Phel\Build\BuildFacade;
use Phel\Build\Domain\Compile\BuildReport;
use Phel\Build\Domain\Compile\PhaseTimingReport;
use Phel\Build\Infrastructure\Timing\PhaseTimingProfilerHook;
use Phel\Lang\Registry;
use Phel\Shared\BuildOptions;
use Phel\Shared\ByteSize;
use Phel\Shared\CompiledFile;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\ResourceUsageFormatter;
use Phel\Shared\ScalarCoercion;
use Phel\Shared\VersionFinder;
use Phel\Shared\VersionResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_filter;
use function count;
use function hrtime;
use function preg_match;
use function sprintf;

/**
 * @method BuildFacade getFacade()
 * @method BuildConfig getConfig()
 *
 * @internal
 */
#[ServiceMap(method: 'getFacade', className: BuildFacade::class)]
#[ServiceMap(method: 'getConfig', className: BuildConfig::class)]
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
        $rawLevel = $input->getOption(self::OPTION_OPTIMIZATION_LEVEL);
        if ($rawLevel !== null && preg_match('/^\d+$/', ScalarCoercion::toString($rawLevel)) !== 1) {
            $output->writeln(sprintf(
                '<error>--optimization-level must be a non-negative integer such as 0 or 2, got "%s".</error>',
                ScalarCoercion::toString($rawLevel),
            ));

            return self::INVALID;
        }

        $this->warnAboutLockedVersion($output);
        $buildOptions = $this->getBuildOptions($input);
        $report = (bool) $input->getOption(self::OPTION_REPORT);
        $failed = false;

        $timingHook = (bool) $input->getOption(self::OPTION_TIMING)
            ? new PhaseTimingProfilerHook()
            : null;
        if ($timingHook instanceof PhaseTimingProfilerHook) {
            Registry::setProfilerHook($timingHook);
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
            $failed = true;
        } catch (Throwable $e) {
            $this->getFacade()->writeStackTrace($output, $e);
            $failed = true;
        } finally {
            if ($timingHook instanceof PhaseTimingProfilerHook) {
                Registry::setProfilerHook(null);
            }
        }

        $output->writeln(new ResourceUsageFormatter()->resourceUsageSinceStartOfRequest());

        // A build that aborted mid-compile emits a partial/empty output tree;
        // exiting 0 makes CI and deploy scripts treat a broken build as green.
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function warnAboutLockedVersion(OutputInterface $output): void
    {
        $lockedVersion = $this->getConfig()->getLockedPhelVersion();
        if ($lockedVersion === null) {
            return;
        }

        $buildingVersion = VersionResolver::current();
        if (str_starts_with($lockedVersion, 'dev-') || str_ends_with($lockedVersion, '-dev')) {
            $lockedReference = $this->getConfig()->getLockedPhelReference();
            $buildingReference = strtolower(VersionResolver::currentReference());
            if ($lockedReference === null || $buildingReference === '') {
                return;
            }

            $lockedRuntimeVersion = new VersionFinder('', $lockedReference)->getVersion();
            if (str_starts_with($buildingReference, $lockedReference) && $buildingVersion === $lockedRuntimeVersion) {
                return;
            }
        } elseif (ltrim($lockedVersion, 'v') === ltrim($buildingVersion, 'v')) {
            return;
        }

        $output->writeln(sprintf(
            "<comment>Warning: building with %s, but composer.lock pins %s. Build with the project's Phel version before deploying.</comment>",
            $buildingVersion,
            $lockedVersion,
        ));
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
                ByteSize::format($entry->bytes),
                $entry->cached ? '(cached)' : '(fresh)',
            ));
        }

        $output->writeln('');
        $output->writeln(sprintf(
            'Namespaces: %d (%d fresh, %d cached) | Total: %s | Time: %.1f ms | Output: %s',
            $report->namespaceCount(),
            $report->freshCount(),
            $report->cachedCount(),
            ByteSize::format($report->totalBytes()),
            $report->durationMs(),
            $this->getFacade()->getOutputDirectory(),
        ));
    }

    private function getBuildOptions(InputInterface $input): BuildOptions
    {
        $rawLevel = $input->getOption(self::OPTION_OPTIMIZATION_LEVEL);

        return new BuildOptions(
            $input->getOption(self::OPTION_CACHE) === true,
            $input->getOption(self::OPTION_SOURCE_MAP) === true,
            $rawLevel === null ? null : ScalarCoercion::toInt($rawLevel),
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
