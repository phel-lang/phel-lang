<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test\Coverage;

use Phel\Shared\Facade\CommandFacadeInterface;

use function ksort;
use function realpath;
use function str_starts_with;

/**
 * Turns raw PHP line coverage into per-`.phel`-file coverage by mapping each
 * compiled PHP line back to its Phel source through the build source maps.
 *
 * Coverable Phel lines come from the source map (every line that produced
 * compiled output), so a loaded-but-unexercised function still counts toward
 * the denominator. Only files under the project source directories are kept;
 * vendor and the bundled core library are excluded.
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
