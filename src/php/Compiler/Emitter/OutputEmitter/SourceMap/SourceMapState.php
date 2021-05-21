<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\SourceMap;

final class SourceMapState
{
    private int $generatedLines = 0;
    private int $generatedColumns = 0;
    private array $mappings = [];

    public function reset(): SourceMapState
    {
        $this->generatedLines = 0;
        $this->generatedColumns = 0;
        $this->mappings = [];

        return $this;
    }

    public function incGeneratedLines(int $amount = 1): SourceMapState
    {
        $this->generatedLines += $amount;
        return $this;
    }

    public function incGeneratedColumns(int $amount = 1): SourceMapState
    {
        $this->generatedColumns += $amount;
        return $this;
    }

    public function getGeneratedLines(): int
    {
        return $this->generatedLines;
    }

    public function getGeneratedColumns(): int
    {
        return $this->generatedColumns;
    }

    public function setGeneratedColumns(int $value): SourceMapState
    {
        $this->generatedColumns = $value;
        return $this;
    }

    public function getMappings(): array
    {
        return $this->mappings;
    }

    public function addMapping(array $mapping): SourceMapState
    {
        $this->mappings[] = $mapping;
        return $this;
    }
}
