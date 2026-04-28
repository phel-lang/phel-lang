<?php

declare(strict_types=1);

namespace Phel\Lang;

final readonly class SourceLocation
{
    public function __construct(
        private string $file,
        private int $line,
        private int $column,
    ) {}

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getColumn(): int
    {
        return $this->column;
    }
}
