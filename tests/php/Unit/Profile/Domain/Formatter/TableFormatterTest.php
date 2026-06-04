<?php

declare(strict_types=1);

namespace PhelTest\Unit\Profile\Domain\Formatter;

use Phel\Profile\Domain\Formatter\TableFormatter;
use Phel\Profile\Domain\ProfileReport;
use Phel\Profile\Domain\SortOrder;
use PHPUnit\Framework\TestCase;

final class TableFormatterTest extends TestCase
{
    public function test_it_reports_no_calls_when_fn_stats_are_empty(): void
    {
        $out = new TableFormatter()->render(new ProfileReport([], [], 0.0), 10, SortOrder::SelfTime, true);

        self::assertStringContainsString('Runtime fn profile: no profiled calls recorded.', $out);
        self::assertStringContainsString('Wall-clock total: 0.00 ms', $out);
    }

    public function test_it_omits_compile_phase_section_when_no_phase_data(): void
    {
        $out = new TableFormatter()->render(new ProfileReport([], [], 0.0), 10, SortOrder::SelfTime, true);

        self::assertStringNotContainsString('Compile-time phases', $out);
    }

    public function test_it_omits_compile_phase_section_when_flag_disabled(): void
    {
        $report = new ProfileReport([], ['src/a.phel' => ['lex' => 1.0]], 0.0);

        $out = new TableFormatter()->render($report, 10, SortOrder::SelfTime, false);

        self::assertStringNotContainsString('Compile-time phases', $out);
    }

    public function test_it_renders_compile_phase_section_and_per_phase_total(): void
    {
        $report = new ProfileReport(
            [],
            ['src/a.phel' => ['lex' => 1.0, 'parse' => 2.0, 'read' => 0.5, 'analyze' => 1.5, 'emit' => 0.0]],
            0.0,
        );

        $out = new TableFormatter()->render($report, 10, SortOrder::SelfTime, true);

        self::assertStringContainsString('Compile-time phases (ms)', $out);
        self::assertStringContainsString('src/a.phel', $out);
        // total = 1 + 2 + 0.5 + 1.5 + 0 = 5.00
        self::assertStringContainsString('5.00', $out);
    }

    public function test_it_sorts_compile_phase_rows_by_descending_total(): void
    {
        $report = new ProfileReport(
            [],
            [
                'small.phel' => ['lex' => 1.0],
                'big.phel' => ['lex' => 9.0],
            ],
            0.0,
        );

        $out = new TableFormatter()->render($report, 10, SortOrder::SelfTime, true);

        self::assertLessThan(
            strpos($out, 'small.phel'),
            strpos($out, 'big.phel'),
            'big.phel (higher total) must appear before small.phel',
        );
    }

    public function test_it_formats_namespaced_fn_name_as_slash_and_dots(): void
    {
        $report = new ProfileReport(
            ['phel\\core\\my_fn' => ['calls' => 1, 'totalNs' => 0, 'selfNs' => 0, 'maxNs' => 0]],
            [],
            0.0,
        );

        $out = new TableFormatter()->render($report, 10, SortOrder::SelfTime, false);

        self::assertStringContainsString('phel.core/my-fn', $out);
    }

    public function test_it_formats_unqualified_fn_name_by_replacing_underscores(): void
    {
        $report = new ProfileReport(
            ['my_global_fn' => ['calls' => 1, 'totalNs' => 0, 'selfNs' => 0, 'maxNs' => 0]],
            [],
            0.0,
        );

        $out = new TableFormatter()->render($report, 10, SortOrder::SelfTime, false);

        self::assertStringContainsString('my-global-fn', $out);
    }

    public function test_it_computes_self_total_avg_and_max_columns(): void
    {
        // calls=2, totalNs=4_000_000 (=4ms, avg 2ms=2000us), selfNs=3_000_000 (=3ms), maxNs=2_500_000 (=2500us)
        $report = new ProfileReport(
            ['app\\fn' => ['calls' => 2, 'totalNs' => 4_000_000, 'selfNs' => 3_000_000, 'maxNs' => 2_500_000]],
            [],
            0.0,
        );

        $out = new TableFormatter()->render($report, 10, SortOrder::SelfTime, false);

        self::assertStringContainsString('3.00', $out);    // self ms
        self::assertStringContainsString('4.00', $out);    // total ms
        self::assertStringContainsString('2000.00', $out); // avg us
        self::assertStringContainsString('2500.00', $out); // max us
        self::assertStringContainsString('Totals: 1 fns, 2 calls, 3.00 ms self.', $out);
    }

    public function test_it_truncates_fn_rows_to_top_n_but_totals_count_all(): void
    {
        $report = new ProfileReport(
            [
                'app\\a' => ['calls' => 1, 'totalNs' => 1_000_000, 'selfNs' => 1_000_000, 'maxNs' => 1_000_000],
                'app\\b' => ['calls' => 1, 'totalNs' => 2_000_000, 'selfNs' => 2_000_000, 'maxNs' => 2_000_000],
                'app\\c' => ['calls' => 1, 'totalNs' => 3_000_000, 'selfNs' => 3_000_000, 'maxNs' => 3_000_000],
            ],
            [],
            0.0,
        );

        $out = new TableFormatter()->render($report, 1, SortOrder::SelfTime, false);

        // Only the top 1 (highest selfNs => app\c) is shown
        self::assertStringContainsString('app/c', $out);
        self::assertStringNotContainsString('app/a', $out);
        self::assertStringContainsString('(top 1, sort by self)', $out);
        // but the totals reflect all three
        self::assertStringContainsString('Totals: 3 fns, 3 calls', $out);
    }

    public function test_it_orders_rows_by_calls_when_sorting_by_calls(): void
    {
        $report = new ProfileReport(
            [
                'app\\few' => ['calls' => 1, 'totalNs' => 9_000_000, 'selfNs' => 9_000_000, 'maxNs' => 9_000_000],
                'app\\many' => ['calls' => 100, 'totalNs' => 1_000_000, 'selfNs' => 1_000_000, 'maxNs' => 100],
            ],
            [],
            0.0,
        );

        $out = new TableFormatter()->render($report, 10, SortOrder::Calls, false);

        self::assertLessThan(
            strpos($out, 'app/few'),
            strpos($out, 'app/many'),
            'app/many (100 calls) must sort before app/few (1 call)',
        );
    }

    public function test_it_shortens_long_source_paths_to_40_chars_with_ellipsis(): void
    {
        $longSource = '/very/deeply/nested/path/to/some/source/file/module.phel';
        $report = new ProfileReport([], [$longSource => ['lex' => 1.0]], 0.0);

        $out = new TableFormatter()->render($report, 10, SortOrder::SelfTime, true);

        // Shortened to '…' + last 39 chars; full original path must not appear
        self::assertStringNotContainsString($longSource, $out);
        self::assertStringContainsString('…', $out);
        self::assertStringContainsString('source/file/module.phel', $out);
    }

    public function test_it_keeps_short_source_paths_unchanged(): void
    {
        $report = new ProfileReport([], ['short.phel' => ['lex' => 1.0]], 0.0);

        $out = new TableFormatter()->render($report, 10, SortOrder::SelfTime, true);

        self::assertStringContainsString('short.phel', $out);
        self::assertStringNotContainsString('…', $out);
    }

    public function test_it_handles_zero_call_fn_without_division_error(): void
    {
        $report = new ProfileReport(
            ['app\\never' => ['calls' => 0, 'totalNs' => 0, 'selfNs' => 0, 'maxNs' => 0]],
            [],
            0.0,
        );

        $out = new TableFormatter()->render($report, 10, SortOrder::SelfTime, false);

        self::assertStringContainsString('app/never', $out);
        // avg us column is 0.00 when calls == 0
        self::assertStringContainsString('0.00', $out);
    }
}
