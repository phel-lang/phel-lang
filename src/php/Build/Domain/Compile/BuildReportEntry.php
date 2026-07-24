<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

/**
 * One namespace line in a {@see BuildReport}: its compiled byte size and
 * whether it was reused from the build cache.
 *
 * @phpstan-type SerializedBuildReportEntry array{namespace: string, bytes: int, cached: bool}
 */
final readonly class BuildReportEntry
{
    public function __construct(
        public string $namespace,
        public int $bytes,
        public bool $cached,
    ) {}

    /**
     * @return SerializedBuildReportEntry
     */
    public function toArray(): array
    {
        return [
            'namespace' => $this->namespace,
            'bytes' => $this->bytes,
            'cached' => $this->cached,
        ];
    }
}
