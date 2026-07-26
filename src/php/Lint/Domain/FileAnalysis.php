<?php

declare(strict_types=1);

namespace Phel\Lint\Domain;

use Phel\Lang\TypeInterface;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Api\ProjectIndex;

/**
 * Immutable data handed to every rule for a single file. Rules must not
 * mutate this — it is shared across the rule pipeline.
 *
 * `semanticDiagnostics` is the cached output of `ApiFacadeInterface::analyzeSource`
 * for this file: rules that consume analyzer diagnostics reuse it so the
 * pipeline only pays for one analyze pass per file.
 *
 * @internal
 */
final readonly class FileAnalysis
{
    /**
     * @param list<bool|float|int|string|TypeInterface|null> $forms               top-level read forms
     * @param list<Diagnostic>                               $semanticDiagnostics analyzer output
     */
    public function __construct(
        public string $uri,
        public string $namespace,
        public string $source,
        public array $forms,
        public ProjectIndex $projectIndex,
        public array $semanticDiagnostics = [],
    ) {}
}
