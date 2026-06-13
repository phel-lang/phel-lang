<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test\Coverage;

use Phel\Run\Application\Test\Coverage\CoverageAggregator;
use Phel\Shared\Facade\CommandFacadeInterface;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class CoverageAggregatorTest extends TestCase
{
    private string $projectDir;

    private string $calcPhel;

    private string $vendorPhel;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/phel-cov-' . uniqid();
        mkdir($this->projectDir . '/src', 0o755, true);
        $this->calcPhel = $this->projectDir . '/src/calc.phel';
        file_put_contents($this->calcPhel, "(ns app.calc)\n(defn add [a b] (+ a b))\n(defn unused [x] x)\n");

        $vendorDir = sys_get_temp_dir() . '/phel-cov-vendor-' . uniqid();
        mkdir($vendorDir, 0o755, true);
        $this->vendorPhel = $vendorDir . '/lib.phel';
        file_put_contents($this->vendorPhel, "(ns lib)\n");
    }

    public function test_maps_covered_and_uncovered_lines_to_phel_source(): void
    {
        // calc.php lines 10,11 map to calc.phel:2 (add); 12,13 to calc.phel:3 (unused).
        $commandFacade = $this->commandFacade([
            '/cache/calc.php' => ['filename' => $this->calcPhel, 'lines' => [10 => 2, 11 => 2, 12 => 3, 13 => 3]],
        ]);

        // Only the add lines executed; unused was never called.
        $raw = ['/cache/calc.php' => [10 => 1, 11 => 1]];

        $report = new CoverageAggregator($commandFacade, [$this->projectDir], 'pcov')->aggregate($raw);

        self::assertCount(1, $report->files());
        $file = $report->files()[0];
        self::assertSame($this->calcPhel, $file->filename);
        self::assertSame(2, $file->coverableCount());      // phel lines 2 and 3
        self::assertSame(1, $file->coveredCount());         // only line 2 covered
        self::assertSame([3], $file->uncoveredLines());     // line 3 (unused) uncovered
        self::assertSame(50.0, $file->percentage());
    }

    public function test_excludes_files_outside_project_dirs(): void
    {
        $commandFacade = $this->commandFacade([
            '/cache/lib.php' => ['filename' => $this->vendorPhel, 'lines' => [5 => 1]],
        ]);

        $report = new CoverageAggregator($commandFacade, [$this->projectDir], 'pcov')->aggregate([
            '/cache/lib.php' => [5 => 1],
        ]);

        self::assertSame([], $report->files());
    }

    public function test_skips_files_without_a_source_map(): void
    {
        $commandFacade = $this->commandFacade([
            '/cache/no-map.php' => ['filename' => '', 'lines' => []],
        ]);

        $report = new CoverageAggregator($commandFacade, [$this->projectDir], 'pcov')->aggregate([
            '/cache/no-map.php' => [1 => 1],
        ]);

        self::assertSame([], $report->files());
    }

    /**
     * @param array<string, array{filename: string, lines: array<int, int>}> $maps
     */
    private function commandFacade(array $maps): CommandFacadeInterface
    {
        $facade = $this->createStub(CommandFacadeInterface::class);
        $facade->method('getCompiledFileLineMap')->willReturnCallback(
            static fn(string $file): array => $maps[$file] ?? ['filename' => '', 'lines' => []],
        );

        return $facade;
    }
}
