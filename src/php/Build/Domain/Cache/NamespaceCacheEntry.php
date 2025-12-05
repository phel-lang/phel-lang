<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

use Phel\Build\Domain\Extractor\NamespaceInformation;

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
    ) {
    }

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
        );
    }

    /**
     * @return array{mtime: int, namespace: string, dependencies: list<string>}
     */
    public function toArray(): array
    {
        return [
            'mtime' => $this->mtime,
            'namespace' => $this->namespace,
            'dependencies' => $this->dependencies,
        ];
    }

    /**
     * @param array{mtime: int, namespace: string, dependencies: list<string>} $data
     */
    public static function fromArray(string $file, array $data): self
    {
        return new self(
            $file,
            $data['mtime'],
            $data['namespace'],
            $data['dependencies'],
        );
    }
}
