<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\SourceAnalyzer;
use Phel\Api\Domain\AnalysisStageInterface;
use Phel\Api\Transfer\Diagnostic;
use PHPUnit\Framework\TestCase;

final class SourceAnalyzerTest extends TestCase
{
    public function test_it_runs_all_stages_and_aggregates_diagnostics(): void
    {
        $stage1 = $this->stageEmitting([
            new Diagnostic('A1', Diagnostic::SEVERITY_WARNING, 'from stage 1', 'u', 1, 1, 1, 2),
        ]);
        $stage2 = $this->stageEmitting([
            new Diagnostic('B1', Diagnostic::SEVERITY_ERROR, 'from stage 2', 'u', 2, 2, 2, 3),
        ]);

        $analyzer = new SourceAnalyzer([$stage1, $stage2]);

        $result = $analyzer->analyze('(ns u)', 'u');

        self::assertCount(2, $result);
        self::assertSame('A1', $result[0]->code);
        self::assertSame('B1', $result[1]->code);
    }

    public function test_it_returns_empty_list_when_no_stages_report(): void
    {
        $analyzer = new SourceAnalyzer([$this->stageEmitting([])]);

        self::assertSame([], $analyzer->analyze('source', 'uri'));
    }

    /**
     * @param list<Diagnostic> $output
     */
    private function stageEmitting(array $output): AnalysisStageInterface
    {
        return new readonly class($output) implements AnalysisStageInterface {
            /**
             * @param list<Diagnostic> $output
             */
            public function __construct(
                private array $output,
            ) {}

            public function run(string $source, string $uri, array &$context): array
            {
                return $this->output;
            }
        };
    }
}
