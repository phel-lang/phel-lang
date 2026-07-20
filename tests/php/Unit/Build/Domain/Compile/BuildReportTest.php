<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Compile;

use Phel\Build\Domain\Compile\BuildReport;
use Phel\Shared\CompiledFile;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function str_repeat;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class BuildReportTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    public function test_aggregates_counts_sizes_and_duration(): void
    {
        $fresh = $this->compiledFile('app.core', 100, cached: false);
        $cached = $this->compiledFile('app.util', 50, cached: true);

        $report = BuildReport::fromCompiledFiles([$fresh, $cached], 12.5);

        self::assertSame(2, $report->namespaceCount());
        self::assertSame(1, $report->freshCount());
        self::assertSame(1, $report->cachedCount());
        self::assertSame(150, $report->totalBytes());
        self::assertSame(12.5, $report->durationMs());
    }

    public function test_entries_carry_namespace_size_and_cached_flag(): void
    {
        $report = BuildReport::fromCompiledFiles(
            [$this->compiledFile('app.core', 42, cached: false)],
            1.0,
        );

        $entries = $report->entries();
        self::assertCount(1, $entries);
        self::assertSame('app.core', $entries[0]->namespace);
        self::assertSame(42, $entries[0]->bytes);
        self::assertFalse($entries[0]->cached);
    }

    public function test_missing_target_file_counts_as_zero_bytes(): void
    {
        $report = BuildReport::fromCompiledFiles(
            [new CompiledFile('src/x.phel', '/does/not/exist.php', 'app.x', false)],
            0.0,
        );

        self::assertSame(0, $report->totalBytes());
        self::assertSame(0, $report->entries()[0]->bytes);
    }

    public function test_empty_build_is_zeroed(): void
    {
        $report = BuildReport::fromCompiledFiles([], 0.0);

        self::assertSame(0, $report->namespaceCount());
        self::assertSame(0, $report->totalBytes());
        self::assertSame([], $report->entries());
    }

    public function test_to_array_shape(): void
    {
        $report = BuildReport::fromCompiledFiles(
            [$this->compiledFile('app.core', 10, cached: false)],
            3.0,
        );

        self::assertSame(
            [
                'namespaces' => 1,
                'fresh' => 1,
                'cached' => 0,
                'total_bytes' => 10,
                'duration_ms' => 3.0,
                'entries' => [
                    ['namespace' => 'app.core', 'bytes' => 10, 'cached' => false],
                ],
            ],
            $report->toArray(),
        );
    }

    private function compiledFile(string $namespace, int $bytes, bool $cached): CompiledFile
    {
        $target = sys_get_temp_dir() . '/phel-report-' . uniqid() . '.php';
        file_put_contents($target, str_repeat('x', $bytes));
        $this->tempFiles[] = $target;

        return new CompiledFile('src/' . $namespace . '.phel', $target, $namespace, $cached);
    }
}
