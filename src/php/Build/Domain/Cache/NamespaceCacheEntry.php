<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

use Phel\Shared\NamespaceInformation;

/**
 * `SerializedNamespaceCacheEntry` is what this entry writes; the read side
 * accepts `PartialNamespaceCacheEntry` because cache files written before
 * `isPrimaryDefinition` existed omit that key (it defaults to true).
 *
 * `recordedAt` is the second the file was read in. `filemtime` has whole-second
 * resolution, so a file whose mtime is not older than that second may have been
 * rewritten after the read without its mtime moving (#3537). Such an entry is
 * "racily clean", as git calls it, and never trusted. The default of 0 makes
 * an entry with no known read time never valid.
 *
 * @phpstan-type SerializedNamespaceCacheEntry array{mtime: int, recordedAt: int, namespace: string, dependencies: list<string>, isPrimaryDefinition: bool}
 * @phpstan-type PartialNamespaceCacheEntry array{mtime: int, recordedAt: int, namespace: string, dependencies: list<string>, isPrimaryDefinition?: bool}
 *
 * @internal
 */
final readonly class NamespaceCacheEntry
{
    /**
     * @param list<string> $dependencies
     */
    public function __construct(
        public string $file,
        public int $mtime,
        public string $namespace,
        public array $dependencies,
        public bool $isPrimaryDefinition = true,
        public int $recordedAt = 0,
    ) {}

    public function isValid(): bool
    {
        if (!file_exists($this->file)) {
            return false;
        }

        $currentMtime = filemtime($this->file);

        return $currentMtime !== false
            && $currentMtime === $this->mtime
            && $currentMtime < $this->recordedAt;
    }

    public function toNamespaceInformation(): NamespaceInformation
    {
        return new NamespaceInformation(
            $this->file,
            $this->namespace,
            $this->dependencies,
            $this->isPrimaryDefinition,
        );
    }

    /**
     * @return SerializedNamespaceCacheEntry
     */
    public function toArray(): array
    {
        return [
            'mtime' => $this->mtime,
            'recordedAt' => $this->recordedAt,
            'namespace' => $this->namespace,
            'dependencies' => $this->dependencies,
            'isPrimaryDefinition' => $this->isPrimaryDefinition,
        ];
    }

    /**
     * @param PartialNamespaceCacheEntry $data
     */
    public static function fromArray(string $file, array $data): self
    {
        return new self(
            $file,
            $data['mtime'],
            $data['namespace'],
            $data['dependencies'],
            $data['isPrimaryDefinition'] ?? true,
            $data['recordedAt'],
        );
    }
}
