<?php

declare(strict_types=1);

namespace PhelTest\Unit\Profile\Domain\Formatter;

use Phel\Profile\Domain\Formatter\JsonFormatter;
use Phel\Profile\Domain\ProfileReport;
use PHPUnit\Framework\TestCase;

use function json_decode;

final class JsonFormatterTest extends TestCase
{
    public function test_it_renders_empty_report_with_top_level_keys(): void
    {
        $json = new JsonFormatter()->render(new ProfileReport([], [], 0.0));

        $decoded = json_decode($json, true);

        self::assertSame(['wall_clock_ms', 'compile_phases', 'fns'], array_keys($decoded));
        self::assertEquals(0, $decoded['wall_clock_ms']);
        self::assertSame([], $decoded['compile_phases']);
        self::assertSame([], $decoded['fns']);
    }

    public function test_it_serializes_fn_records_with_ns_and_ms_figures(): void
    {
        $report = new ProfileReport(
            ['app\\main' => ['calls' => 4, 'totalNs' => 2_500_000, 'selfNs' => 1_500_000, 'maxNs' => 800_000]],
            [],
            12.5,
        );

        $decoded = json_decode(new JsonFormatter()->render($report), true);

        self::assertSame(12.5, $decoded['wall_clock_ms']);
        self::assertCount(1, $decoded['fns']);
        $fn = $decoded['fns'][0];
        self::assertSame('app\\main', $fn['bound_to']);
        self::assertSame(4, $fn['calls']);
        self::assertSame(1_500_000, $fn['self_ns']);
        self::assertSame(2_500_000, $fn['total_ns']);
        self::assertSame(800_000, $fn['max_ns']);
        self::assertSame(1.5, $fn['self_ms']);
        self::assertSame(2.5, $fn['total_ms']);
    }

    public function test_it_flattens_compile_phases_with_source_key(): void
    {
        $report = new ProfileReport(
            [],
            ['src/foo.phel' => ['lex' => 1.5, 'parse' => 2.25]],
            5.0,
        );

        $decoded = json_decode(new JsonFormatter()->render($report), true);

        self::assertCount(1, $decoded['compile_phases']);
        self::assertSame(
            ['source' => 'src/foo.phel', 'lex' => 1.5, 'parse' => 2.25],
            $decoded['compile_phases'][0],
        );
    }

    public function test_it_does_not_escape_slashes_in_output(): void
    {
        $report = new ProfileReport([], ['a/b/c.phel' => ['lex' => 1.0]], 0.0);

        $json = new JsonFormatter()->render($report);

        self::assertStringContainsString('a/b/c.phel', $json);
        self::assertStringNotContainsString('a\\/b', $json);
    }
}
