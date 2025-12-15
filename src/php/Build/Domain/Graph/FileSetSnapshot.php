<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Graph;

final readonly class FileSetSnapshot
{
    /**
     * @param array<string, int> $files       file path => mtime
     * @param list<string>       $directories directories that were scanned
     */
    public function __construct(
        public array $files,
        public array $directories,
        public int $createdAt,
    ) {
    }

    public function hasFile(string $path): bool
    {
        return isset($this->files[$path]);
    }

    public function getMtime(string $path): ?int
    {
        return $this->files[$path] ?? null;
    }

    /**
     * @return array{files: array<string, int>, directories: list<string>, created_at: int}
     */
    public function toArray(): array
    {
        return [
            'files' => $this->files,
            'directories' => $this->directories,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * @param array{files: array<string, int>, directories: list<string>, created_at: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['files'],
            $data['directories'],
            $data['created_at'],
        );
    }
}
