<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use Phel\Shared\CompiledFile;

use function array_map;
use function count;
use function filesize;
use function is_file;

/**
 * Summary of a `phel build` run: namespace count, per-namespace compiled size,
 * total size, fresh/cached breakdown, and wall-clock duration. Surfaced by
 * `phel build --report` to spot bloat and verify CI builds.
 */
final readonly class BuildReport
{
    /**
     * @param list<BuildReportEntry> $entries
     */
    private function __construct(
        private array $entries,
        private int $freshCount,
        private int $cachedCount,
        private int $totalBytes,
        private float $durationMs,
    ) {}

    /**
     * @param list<CompiledFile> $files
     */
    public static function fromCompiledFiles(array $files, float $durationMs): self
    {
        $entries = [];
        $fresh = 0;
        $cached = 0;
        $totalBytes = 0;

        foreach ($files as $file) {
            $target = $file->getTargetFile();
            $bytes = $target !== '' && is_file($target) ? (int) filesize($target) : 0;
            $totalBytes += $bytes;

            if ($file->isCached()) {
                ++$cached;
            } else {
                ++$fresh;
            }

            $entries[] = new BuildReportEntry($file->getNamespace(), $bytes, $file->isCached());
        }

        return new self($entries, $fresh, $cached, $totalBytes, $durationMs);
    }

    /**
     * @return list<BuildReportEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function namespaceCount(): int
    {
        return count($this->entries);
    }

    public function freshCount(): int
    {
        return $this->freshCount;
    }

    public function cachedCount(): int
    {
        return $this->cachedCount;
    }

    public function totalBytes(): int
    {
        return $this->totalBytes;
    }

    public function durationMs(): float
    {
        return $this->durationMs;
    }

    /**
     * @return array{
     *     namespaces: int,
     *     fresh: int,
     *     cached: int,
     *     total_bytes: int,
     *     duration_ms: float,
     *     entries: list<array{namespace: string, bytes: int, cached: bool}>
     * }
     */
    public function toArray(): array
    {
        return [
            'namespaces' => $this->namespaceCount(),
            'fresh' => $this->freshCount,
            'cached' => $this->cachedCount,
            'total_bytes' => $this->totalBytes,
            'duration_ms' => $this->durationMs,
            'entries' => array_map(
                static fn(BuildReportEntry $e): array => $e->toArray(),
                $this->entries,
            ),
        ];
    }
}
