<?php

declare(strict_types=1);

namespace Phel\Compiler\Compiler;

final class CompileOptions
{
    public const DEFAULT_SOURCE = 'string';
    public const DEFAULT_STARTING_LINE = 1;
    public const DEFAULT_ENABLE_SOURCE_MAPS = true;

    private string $source = self::DEFAULT_SOURCE;
    private int $startingLine = self::DEFAULT_STARTING_LINE;
    private bool $isEnableSourceMaps = self::DEFAULT_ENABLE_SOURCE_MAPS;

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getStartingLine(): int
    {
        return $this->startingLine;
    }

    public function setStartingLine(int $startingLine): self
    {
        $this->startingLine = $startingLine;

        return $this;
    }

    public function isSourceMapsEnabled(): bool
    {
        return $this->isEnableSourceMaps;
    }

    public function setIsEnabledSourceMaps(bool $isEnableSourceMaps): self
    {
        $this->isEnableSourceMaps = $isEnableSourceMaps;

        return $this;
    }
}
