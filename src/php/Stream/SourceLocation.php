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

    public function setFile(string $file) {
        $this->file = $file;
    }

    public function getFile() {
        return $this->file;
    }

    public function setLine(int $line) {
        $this->line = $line;
    }

    public function getLine() {
        return $this->line;
    }

    public function setColumn(int $column) {
        $this->column = $column;
    }

    public function getColumn() {
        return $this->column;
    }
}