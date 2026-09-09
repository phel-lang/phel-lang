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
        self::assertSame(100.0, $none->coveredMsi());
        self::assertStringContainsString('Coverage: none (every mutant ran the whole suite)', $none->toText());
        self::assertStringContainsString('Coverage: pcov (each mutant ran only the tests that reach its definition)', $withCoverage->toText());
        self::assertStringNotContainsString('Warning:', $withCoverage->toText());
    }

    public function test_a_run_that_reached_no_mutant_scores_zero_and_fails_every_floor(): void
    {
        $report = new MutationReport([
            $this->outcome(MutantVerdict::NotCovered),
            $this->outcome(MutantVerdict::NotCovered),
            $this->outcome(MutantVerdict::NotCovered),
        ], 0.0, 'pcov');

        self::assertSame(0.0, $report->msi());
        self::assertSame(0.0, $report->coveredMsi());
        self::assertFalse($report->meetsMinimum(null, 84.0));
        self::assertFalse($report->meetsMinimum(78.0, 84.0));
        self::assertStringContainsString(
            'Warning: pcov reached no mutant at all, so no test ran and nothing was measured.',
            $report->toText(),
        );
    }

    public function test_a_mutant_that_did_not_compile_does_not_hide_a_run_that_reached_nothing(): void
    {
        $report = new MutationReport([
            $this->outcome(MutantVerdict::NotCovered),
            $this->outcome(MutantVerdict::Error),
        ], 0.0, 'pcov');

        self::assertSame(0.0, $report->coveredMsi());
        self::assertFalse($report->meetsMinimum(null, 84.0));
        self::assertStringContainsString('Warning: pcov reached no mutant at all', $report->toText());
    }

    public function test_a_single_killed_mutant_among_uncovered_ones_still_scores_the_covered_half(): void
    {
        $report = new MutationReport([
            $this->outcome(MutantVerdict::Killed),
            $this->outcome(MutantVerdict::NotCovered),
            $this->outcome(MutantVerdict::NotCovered),
            $this->outcome(MutantVerdict::NotCovered),
        ], 0.0, 'pcov');

        // One mutant was reached and it died: the covered half is perfect even
        // though three quarters of the run went unreached.
        self::assertSame(25.0, $report->msi());
        self::assertSame(100.0, $report->coveredMsi());
        self::assertTrue($report->meetsMinimum(null, 84.0));
        self::assertStringNotContainsString('Warning:', $report->toText());
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
