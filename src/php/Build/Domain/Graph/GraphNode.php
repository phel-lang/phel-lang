<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Graph;

use Phel\Build\Domain\Extractor\NamespaceInformation;

final readonly class GraphNode
{
    /**
     * @param list<string> $dependencies
     */
    public function __construct(
        public string $file,
        public string $namespace,
        public int $mtime,
        public array $dependencies,
    ) {
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
     * @return array{file: string, namespace: string, mtime: int, dependencies: list<string>}
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'namespace' => $this->namespace,
            'mtime' => $this->mtime,
            'dependencies' => $this->dependencies,
        ];
    }

    /**
     * @param array{file: string, namespace: string, mtime: int, dependencies: list<string>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['file'],
            $data['namespace'],
            $data['mtime'],
            $data['dependencies'],
        );
    }
}
