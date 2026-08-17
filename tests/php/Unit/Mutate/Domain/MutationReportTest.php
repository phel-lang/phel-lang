<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Domain;

use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\MutantResult;
use Phel\Mutate\Domain\MutantVerdict;
use Phel\Mutate\Domain\MutationReport;
use PHPUnit\Framework\TestCase;

use function json_decode;

final class MutationReportTest extends TestCase
{
    public function test_msi_counts_uncovered_mutants_against_the_suite_but_covered_msi_does_not(): void
    {
        $report = new MutationReport([
            $this->outcome(MutantVerdict::Killed),
            $this->outcome(MutantVerdict::Killed),
            $this->outcome(MutantVerdict::Timeout),
            $this->outcome(MutantVerdict::Survived),
            $this->outcome(MutantVerdict::NotCovered),
            $this->outcome(MutantVerdict::NotCovered),
            $this->outcome(MutantVerdict::Error),
        ], 0.5, 'xdebug');

        // detected 3, survived 1, not covered 2: 3/6 overall, 3/4 among the covered.
        self::assertSame(50.0, $report->msi());
        self::assertSame(75.0, $report->coveredMsi());
        self::assertTrue($report->meetsMinimum(50.0));
        self::assertFalse($report->meetsMinimum(50.1));
    }

    public function test_an_empty_report_scores_100_and_the_text_names_the_coverage_mode(): void
    {
        $none = new MutationReport([], 0.1);
        $withCoverage = new MutationReport([], 0.1, 'pcov');

        self::assertSame(100.0, $none->msi());
        self::assertStringContainsString('Coverage: none (every mutant ran the whole suite)', $none->toText());
        self::assertStringContainsString('Coverage: pcov (each mutant ran only the tests that reach its definition)', $withCoverage->toText());
    }

    public function test_the_text_report_lists_survivors_and_uncovered_mutants_by_location(): void
    {
        $report = new MutationReport([
            $this->outcome(MutantVerdict::Survived, 'compare', '(< a b) -> (<= a b)', 12),
            $this->outcome(MutantVerdict::NotCovered, 'arith', '(+ a b) -> (- a b)', 20),
        ], 0.2, 'xdebug');

        $text = $report->toText();

        self::assertStringContainsString("Survived:\n  /src/calc.phel:12 [compare] (< a b) -> (<= a b)", $text);
        self::assertStringContainsString("Not covered by any test:\n  /src/calc.phel:20 [arith] (+ a b) -> (- a b)", $text);
    }

    public function test_the_json_report_carries_totals_and_a_diff_per_mutant(): void
    {
        $report = new MutationReport([$this->outcome(MutantVerdict::Killed, 'arith', '(+ a b) -> (- a b)', 4)], 0.25, 'xdebug');

        $decoded = json_decode($report->toJson(), true);

        self::assertIsArray($decoded);
        self::assertSame('xdebug', $decoded['coverage']);
        self::assertSame(['mutants' => 1, 'killed' => 1, 'survived' => 0, 'notCovered' => 0, 'errors' => 0, 'timeouts' => 0, 'msi' => 100, 'coveredMsi' => 100], $decoded['totals']);
        self::assertIsArray($decoded['mutants']);
        self::assertSame("-4: (defn add [a b] (+ a b))\n+4: (defn add [a b] (- a b))", $decoded['mutants'][0]['diff']);
    }

    private function outcome(MutantVerdict $verdict, string $mutator = 'arith', string $description = '(+ a b) -> (- a b)', int $line = 4): MutantResult
    {
        return new MutantResult(
            new Mutant('/src/calc.phel', 'app.calc', 'add', $line, 2, $line, $mutator, $description, '(defn add [a b] (+ a b))', '(defn add [a b] (- a b))'),
            $verdict,
            0.01,
        );
    }
}
