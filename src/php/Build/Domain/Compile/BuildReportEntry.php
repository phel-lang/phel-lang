<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

/**
 * One namespace line in a {@see BuildReport}: its compiled byte size and
 * whether it was reused from the build cache.
 */
final readonly class BuildReportEntry
{
    public function __construct(
        public string $namespace,
        public int $bytes,
        public bool $cached,
    ) {}

    /**
     * @return array{namespace: string, bytes: int, cached: bool}
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
