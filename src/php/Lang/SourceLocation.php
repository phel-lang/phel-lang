<?php

declare(strict_types=1);

namespace Phel\Lang;

final class SourceLocation
{
    private string $file;
    private int $line;
    private int $column;

    public function __construct(string $file, int $line, int $column)
    {
        $this->file = $file;
        $this->line = $line;
        $this->column = $column;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): void
    {
        $this->file = $file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function setLine(int $line): void
    {
        $this->line = $line;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    public function setColumn(int $column): void
    {
        $this->column = $column;
    }
}
