<?php

declare(strict_types=1);

namespace Phel\Lint\Application;

use Phel\Lint\Application\Cache\LintCache;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Domain\Exception\LintSourceException;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Transfer\LintResult;
use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\Facade\ApiFacadeInterface;

use function file_get_contents;
use function is_dir;

/**
 * Orchestrator: takes a mix of paths + settings, expands to `.phel` files,
 * fetches a project index, analyses each file, runs the rule pipeline,
 * and returns a single `LintResult`.
 *
 * Caching is optional: when a `LintCache` is injected, files whose hash
 * and rule fingerprint match the cache bypass the pipeline entirely.
 */
final readonly class LintRunner
{
    public function __construct(
        private ApiFacadeInterface $apiFacade,
        private FileCollector $fileCollector,
        private SourceReader $sourceReader,
        private RulePipeline $pipeline,
        private ?LintCache $cache = null,
    ) {}

    /**
     * @param list<string> $paths
     *
     * @throws LintSourceException when a collected file cannot be read, or a
     *                             listed directory cannot be walked
     */
    public function run(array $paths, RuleSettings $settings): LintResult
    {
        $files = $this->fileCollector->collect($paths);
        if ($files === []) {
            return new LintResult([]);
        }

        $projectIndex = $this->buildProjectIndex($paths);

        $allDiagnostics = [];
        foreach ($files as $file) {
            $cached = $this->cache?->get($file);
            if ($cached !== null) {
                foreach ($cached as $diagnostic) {
                    $allDiagnostics[] = $diagnostic;
                }

                continue;
            }

            // Suppress the warning and raise instead: an unreadable file must
            // not be skipped, or the run reports it as clean and exits 0.
            $source = @file_get_contents($file);
            if ($source === false) {
                throw LintSourceException::cannotRead($file);
            }

            $read = $this->sourceReader->read($source, $file);
            $semantic = $this->apiFacade->analyzeSource($source, $file);

            $analysis = new FileAnalysis(
                uri: $file,
                namespace: $read['namespace'],
                source: $source,
                forms: $read['forms'],
                projectIndex: $projectIndex,
                semanticDiagnostics: $semantic,
            );

            $fileDiagnostics = $this->pipeline->run($analysis, $settings);
            $this->cache?->put($file, $fileDiagnostics);

            foreach ($fileDiagnostics as $diagnostic) {
                $allDiagnostics[] = $diagnostic;
            }
        }

        $this->cache?->flush();

        return new LintResult($allDiagnostics);
    }

    /**
     * Builds the project-wide symbol index that rules consume for cross-file
     * resolution. Only directories are indexed: individual files are already
     * analysed in the main loop, so passing a directory lets the index cover
     * sibling definitions. When no directories are given the index is empty
     * and rules degrade gracefully (no cross-file resolution).
     *
     * @param list<string> $paths
     */
    private function buildProjectIndex(array $paths): ProjectIndex
    {
        $dirs = [];
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $dirs[] = $path;
            }
        }

        if ($dirs === []) {
            return new ProjectIndex([], []);
        }

        return $this->apiFacade->indexProject($dirs);
    }
}
