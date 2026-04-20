<?php

declare(strict_types=1);

namespace Phel\Lint\Application;

use Phel\Api\ApiFacade;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lint\Application\Cache\LintCache;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Transfer\LintResult;

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
        private ApiFacade $apiFacade,
        private FileCollector $fileCollector,
        private SourceReader $sourceReader,
        private RulePipeline $pipeline,
        private ?LintCache $cache = null,
    ) {}

    /**
     * @param list<string> $paths
     */
    public function run(array $paths, RuleSettings $settings): LintResult
    {
        $files = $this->fileCollector->collect($paths);
        if ($files === []) {
            return new LintResult([], []);
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

            $source = @file_get_contents($file);
            if ($source === false) {
                continue;
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

        return new LintResult($allDiagnostics, $files);
    }

    /**
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
