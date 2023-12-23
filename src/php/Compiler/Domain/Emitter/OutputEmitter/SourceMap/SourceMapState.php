<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap;

final class SourceMapState
{
    private int $generatedLines = 0;

    private int $generatedColumns = 0;

    private array $mappings = [];

    public function reset(): self
    {
        $this->generatedLines = 0;
        $this->generatedColumns = 0;
        $this->mappings = [];

        return $this;
    }

    public function incGeneratedLines(int $amount = 1): self
    {
        $this->generatedLines += $amount;
        return $this;
    }

    public function incGeneratedColumns(int $amount = 1): self
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

    public function setGeneratedColumns(int $value): self
    {
        $this->generatedColumns = $value;
        return $this;
    }

    public function getMappings(): array
    {
        return $this->mappings;
    }

    public function addMapping(array $mapping): self
    {
        $this->mappings[] = $mapping;
        return $this;
    }
}
