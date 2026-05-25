<?php

declare(strict_types=1);

namespace Phel\Shared;

final class CompileOptions
{
    public const string DEFAULT_SOURCE = 'string';

    public const int DEFAULT_STARTING_LINE = 1;

    public const bool DEFAULT_ENABLE_SOURCE_MAPS = true;

    public const bool DEFAULT_EMIT_ONLY = false;

    private string $source = self::DEFAULT_SOURCE;

    private int $startingLine = self::DEFAULT_STARTING_LINE;

    private bool $isEnableSourceMaps = self::DEFAULT_ENABLE_SOURCE_MAPS;

    private bool $emitOnly = self::DEFAULT_EMIT_ONLY;

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

    public function isEmitOnly(): bool
    {
        return $this->emitOnly;
    }

    public function setEmitOnly(bool $emitOnly): self
    {
        $this->emitOnly = $emitOnly;

        return $this;
    }
}
