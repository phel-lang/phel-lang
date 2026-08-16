<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test\Coverage;

use Phel\Shared\Facade\CommandFacadeInterface;

use function array_key_exists;
use function array_keys;
use function ksort;
use function realpath;
use function sort;
use function str_starts_with;

/**
 * Turns raw PHP line coverage into per-`.phel`-file coverage by mapping each
 * compiled PHP line back to its Phel source through the build source maps.
 *
 * Coverable Phel lines come from the source map (every line that produced
 * compiled output), so a loaded-but-unexercised function still counts toward
 * the denominator. Only files under the project source directories are kept;
 * vendor and the bundled core library are excluded.
 *
 * @internal
 */
final readonly class CoverageAggregator
{
    /**
     * @param list<string> $projectDirs
     */
    public function __construct(
        private CommandFacadeInterface $commandFacade,
        private array $projectDirs,
        private string $driver,
    ) {}

    /**
     * @param array<string, array<int, int>> $rawCoverage compiledPhpFile => [phpLine => hitCount]
     */
    public function aggregate(array $rawCoverage): CoverageReport
    {
        $normalizedDirs = $this->normalizedProjectDirs();

        /** @var array<string, array<int, bool>> $perPhelFile phelFile => [phelLine => covered] */
        $perPhelFile = [];

        foreach ($rawCoverage as $phpFile => $hits) {
            $map = $this->commandFacade->getCompiledFileLineMap($phpFile);
            $phelFile = $map['filename'];
            if ($phelFile === '') {
                continue;
            }

            if (!$this->isProjectFile($phelFile, $normalizedDirs)) {
                continue;
            }

            foreach ($map['lines'] as $phpLine => $phelLine) {
                $covered = ($hits[$phpLine] ?? 0) > 0;
                $existing = $perPhelFile[$phelFile][$phelLine] ?? false;
                $perPhelFile[$phelFile][$phelLine] = $existing || $covered;
            }
        }

        ksort($perPhelFile);

        $files = [];
        foreach ($perPhelFile as $phelFile => $coverable) {
            $files[] = new CoverageFile($phelFile, $coverable);
        }

        return new CoverageReport($files, $this->driver);
    }

    /**
     * Per-test attribution: which project `.phel` lines each test executed,
     * and which tests executed each line. Every compiled PHP file is mapped
     * to its source once, however many tests hit it.
     *
     * @param array<string, array<string, list<int>>> $hitLinesByTest testId => compiledPhpFile => PHP lines with hits
     */
    public function attribute(array $hitLinesByTest): PerTestCoverageReport
    {
        $normalizedDirs = $this->normalizedProjectDirs();
        /** @var array<string, array{filename: string, lines: array<int, int>}|null> $mapsByPhpFile null = not a project file */
        $mapsByPhpFile = [];
        /** @var array<string, array<string, array<int, true>>> $linesByTest */
        $linesByTest = [];
        /** @var array<string, array<int, array<string, true>>> $testsByLine */
        $testsByLine = [];

        foreach ($hitLinesByTest as $testId => $hitsByPhpFile) {
            $linesByTest[$testId] = [];
            foreach ($hitsByPhpFile as $phpFile => $phpLines) {
                if (!array_key_exists($phpFile, $mapsByPhpFile)) {
                    $mapsByPhpFile[$phpFile] = $this->projectLineMap($phpFile, $normalizedDirs);
                }

                $map = $mapsByPhpFile[$phpFile];
                if ($map === null) {
                    continue;
                }

                foreach ($phpLines as $phpLine) {
                    $phelLine = $map['lines'][$phpLine] ?? null;
                    if ($phelLine === null) {
                        continue;
                    }

                    $linesByTest[$testId][$map['filename']][$phelLine] = true;
                    $testsByLine[$map['filename']][$phelLine][$testId] = true;
                }
            }
        }

        return new PerTestCoverageReport(
            $this->sortedLinesByTest($linesByTest),
            $this->sortedTestsByLine($testsByLine),
            $this->driver,
        );
    }

    /**
     * @param list<string> $normalizedDirs
     *
     * @return array{filename: string, lines: array<int, int>}|null
     */
    private function projectLineMap(string $phpFile, array $normalizedDirs): ?array
    {
        $map = $this->commandFacade->getCompiledFileLineMap($phpFile);
        if ($map['filename'] === '' || !$this->isProjectFile($map['filename'], $normalizedDirs)) {
            return null;
        }

        return $map;
    }

    /**
     * @param array<string, array<string, array<int, true>>> $linesByTest
     *
     * @return array<string, array<string, list<int>>>
     */
    private function sortedLinesByTest(array $linesByTest): array
    {
        ksort($linesByTest);
        $out = [];
        foreach ($linesByTest as $testId => $files) {
            ksort($files);
            $out[$testId] = [];
            foreach ($files as $file => $lines) {
                $sorted = array_keys($lines);
                sort($sorted);
                $out[$testId][$file] = $sorted;
            }
        }

        return $out;
    }

    /**
     * @param array<string, array<int, array<string, true>>> $testsByLine
     *
     * @return array<string, array<int, list<string>>>
     */
    private function sortedTestsByLine(array $testsByLine): array
    {
        ksort($testsByLine);
        $out = [];
        foreach ($testsByLine as $file => $byLine) {
            ksort($byLine);
            $out[$file] = [];
            foreach ($byLine as $line => $tests) {
                $sorted = array_keys($tests);
                sort($sorted);
                $out[$file][$line] = $sorted;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $normalizedDirs
     */
    private function isProjectFile(string $phelFile, array $normalizedDirs): bool
    {
        $real = realpath($phelFile);
        $candidate = $real === false ? $phelFile : $real;
        return array_any($normalizedDirs, static fn(string $dir): bool => str_starts_with($candidate, $dir));
    }

    /**
     * @return list<string>
     */
    private function normalizedProjectDirs(): array
    {
        $dirs = [];
        foreach ($this->projectDirs as $dir) {
            $real = realpath($dir);
            if ($real !== false) {
                $dirs[] = $real;
            }
        }

        return $dirs;
    }
}
