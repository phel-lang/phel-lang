<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Graph;

final readonly class FileSetDiff
{
    /**
     * @param list<string> $added    new files
     * @param list<string> $modified files with changed mtime
     * @param list<string> $deleted  files that no longer exist
     */
    public function __construct(
        public array $added,
        public array $modified,
        public array $deleted,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->added === []
            && $this->modified === []
            && $this->deleted === [];
    }

    /**
     * @return list<string>
     */
    public function getChangedFiles(): array
    {
        return [...$this->added, ...$this->modified];
    }
}
