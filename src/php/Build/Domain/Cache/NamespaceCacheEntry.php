<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

use Phel\Shared\NamespaceInformation;

/**
 * `SerializedNamespaceCacheEntry` is what this entry writes; the read side
 * accepts `PartialNamespaceCacheEntry` because cache files written before
 * `isPrimaryDefinition` existed omit that key (it defaults to true).
 *
 * @phpstan-type SerializedNamespaceCacheEntry array{mtime: int, namespace: string, dependencies: list<string>, isPrimaryDefinition: bool}
 * @phpstan-type PartialNamespaceCacheEntry array{mtime: int, namespace: string, dependencies: list<string>, isPrimaryDefinition?: bool}
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
    ) {}

    public function isValid(): bool
    {
        if (!file_exists($this->file)) {
            return false;
        }

        $currentMtime = filemtime($this->file);

        return $currentMtime !== false && $currentMtime === $this->mtime;
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
        );
    }
}
