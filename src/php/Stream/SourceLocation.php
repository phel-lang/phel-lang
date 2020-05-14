<?php

namespace Phel\Stream;

class SourceLocation {

    /**
     * @var string
     */
    protected $file;

    /**
     * @var int
     */
    protected $line;

    /**
     * @var int
     */
    protected $column;

    public function __construct(string $file, int $line, int $column)
    {
        $this->file = $file;
        $this->line = $line;
        $this->column = $column;
    }

    public function setFile(string $file): void {
        $this->file = $file;
    }

    public function getFile(): string {
        return $this->file;
    }

    public function setLine(int $line): void {
        $this->line = $line;
    }

    public function getLine(): int {
        return $this->line;
    }

    public function setColumn(int $column): void {
        $this->column = $column;
    }

    public function getColumn(): int {
        return $this->column;
    }
}