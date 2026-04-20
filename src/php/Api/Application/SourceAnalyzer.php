<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\AnalysisStageInterface;
use Phel\Api\Domain\SourceAnalyzerInterface;
use Phel\Api\Transfer\Diagnostic;

/**
 * Pipeline runner that executes each analysis stage in sequence,
 * accumulating diagnostics. Stages are pluggable via the constructor.
 */
final readonly class SourceAnalyzer implements SourceAnalyzerInterface
{
    /**
     * @param list<AnalysisStageInterface> $stages
     */
    public function __construct(
        private array $stages,
    ) {}

    /**
     * @return list<Diagnostic>
     */
    public function analyze(string $source, string $uri): array
    {
        $diagnostics = [];
        $context = [];

        foreach ($this->stages as $stage) {
            foreach ($stage->run($source, $uri, $context) as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
        }

        return $diagnostics;
    }
}
