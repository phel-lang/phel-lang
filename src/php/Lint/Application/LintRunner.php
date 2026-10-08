<?php

declare(strict_types=1);

namespace Phel\Lint\Application;

use Phel\Lang\SourceLocation;
use Phel\Lint\Application\Cache\LintCache;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Application\Rule\NamespaceForm;
use Phel\Lint\Domain\Exception\LintSourceException;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Transfer\LintResult;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\LintRuleCodes;

use function array_any;
use function array_filter;
use function array_values;
use function file_get_contents;
use function is_dir;

/**
 * Orchestrator: takes a mix of paths + settings, expands to `.phel` files,
 * fetches a project index, analyses each file, runs the rule pipeline,
 * and returns a single `LintResult`.
 *
 * Caching is optional: when a `LintCache` is injected, files whose hash
 * and rule fingerprint match the cache bypass the pipeline entirely.
 *
 * @internal
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
                namespace: $read->namespace,
                source: $source,
                forms: $read->forms,
                projectIndex: $projectIndex,
                semanticDiagnostics: $semantic,
            );

            $ruleDiagnostics = $this->pipeline->run($analysis, $settings);

            // A file that stopped reading was never fully seen, so no rule can
            // have an opinion about the part that is missing. The analyzer
            // already worded why it stopped, with a code and a location, and
            // that is the one thing worth saying about the file. Without it
            // the run reports the file as clean and exits 0 (#3292).
            if ($read->failed) {
                $passedThrough = $semantic;
            } else {
                // Like a syntax error, a superseded form stops `phel run`, so
                // it is reported under the analyzer's code, with no rule to
                // switch it off (#3456). An `ns` form the analyzer rejects is
                // kept the same way, unless a dedicated rule reported it (#3457).
                $superseded = $this->supersededForms($semantic);
                $passedThrough = [
                    ...$superseded,
                    ...$this->nsFormErrors(
                        $read->forms,
                        $semantic,
                        [...$superseded, ...$this->withoutCompileErrors($ruleDiagnostics)],
                    ),
                ];
            }

            $fileDiagnostics = [
                ...$passedThrough,
                ...$this->withoutCoveredCompileErrors($ruleDiagnostics, $passedThrough),
            ];

            // A rule crash is a fact about the linter, not about the file, and
            // fixing it changes neither the file hash nor the rule fingerprint.
            // Caching it would replay a stale internal error until the source
            // happens to change, so this file is simply re-linted next run.
            if (!$this->hasInternalError($fileDiagnostics)) {
                $this->cache?->put($file, $fileDiagnostics);
            }

            foreach ($fileDiagnostics as $diagnostic) {
                $allDiagnostics[] = $diagnostic;
            }
        }

        $this->cache?->flush();

        return new LintResult($allDiagnostics);
    }

    /**
     * @param list<mixed>      $forms
     * @param list<Diagnostic> $semantic
     * @param list<Diagnostic> $ruleDiagnostics
     *
     * @return list<Diagnostic>
     */
    private function nsFormErrors(array $forms, array $semantic, array $ruleDiagnostics): array
    {
        $nsForm = NamespaceForm::find($forms);
        $start = $nsForm?->getStartLocation();
        $end = $nsForm?->getEndLocation();
        if (!$start instanceof SourceLocation || !$end instanceof SourceLocation) {
            return [];
        }

        $reported = [];
        foreach ($ruleDiagnostics as $diagnostic) {
            $reported[$diagnostic->startLine . ':' . $diagnostic->startCol . ':' . $diagnostic->message] = true;
        }

        return array_values(array_filter(
            $semantic,
            static fn(Diagnostic $d): bool => $d->startLine >= $start->getLine()
                && $d->startLine <= $end->getLine()
                && !isset($reported[$d->startLine . ':' . $d->startCol . ':' . $d->message]),
        ));
    }

    /**
     * @param list<Diagnostic> $diagnostics
     *
     * @return list<Diagnostic>
     */
    private function supersededForms(array $diagnostics): array
    {
        return array_values(array_filter(
            $diagnostics,
            static fn(Diagnostic $diagnostic): bool => $diagnostic->code === ErrorCode::SUPERSEDED_FORM->value,
        ));
    }

    /**
     * @param list<Diagnostic> $diagnostics
     *
     * @return list<Diagnostic>
     */
    private function withoutCompileErrors(array $diagnostics): array
    {
        return array_values(array_filter(
            $diagnostics,
            static fn(Diagnostic $diagnostic): bool => $diagnostic->code !== LintRuleCodes::COMPILE_ERROR,
        ));
    }

    /**
     * The analyzer stops at the first error in a top-level form, so an error
     * another rule or a pass-through reports inside a `phel/compile-error`
     * span is the same mistake: `(let [a] a)` is the analyzer's `PHEL008` on
     * the whole form and `phel/invalid-destructuring` on its vector.
     *
     * @param list<Diagnostic> $ruleDiagnostics
     * @param list<Diagnostic> $passedThrough
     *
     * @return list<Diagnostic>
     */
    private function withoutCoveredCompileErrors(array $ruleDiagnostics, array $passedThrough): array
    {
        $otherErrors = array_filter(
            [...$passedThrough, ...$this->withoutCompileErrors($ruleDiagnostics)],
            static fn(Diagnostic $diagnostic): bool => $diagnostic->severity === Diagnostic::SEVERITY_ERROR,
        );

        return array_values(array_filter(
            $ruleDiagnostics,
            static fn(Diagnostic $diagnostic): bool => $diagnostic->code !== LintRuleCodes::COMPILE_ERROR
                || !array_any($otherErrors, static fn(Diagnostic $other): bool => self::startsInside($other, $diagnostic)),
        ));
    }

    private static function startsInside(Diagnostic $inner, Diagnostic $outer): bool
    {
        $start = [$inner->startLine, $inner->startCol];

        return $start >= [$outer->startLine, $outer->startCol]
            && $start <= [$outer->endLine, $outer->endCol];
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    private function hasInternalError(array $diagnostics): bool
    {
        return array_any(
            $diagnostics,
            static fn(Diagnostic $diagnostic): bool => $diagnostic->code === LintRuleCodes::INTERNAL_ERROR,
        );
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
