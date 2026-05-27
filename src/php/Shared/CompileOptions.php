<?php

declare(strict_types=1);

namespace Phel\Shared;

final class CompileOptions
{
    public const string DEFAULT_SOURCE = 'string';

    public const int DEFAULT_STARTING_LINE = 1;

    public const bool DEFAULT_ENABLE_SOURCE_MAPS = true;

    public const bool DEFAULT_EMIT_ONLY = false;

    /**
     * Roadmap of optimisation levels gated behind this option:
     *
     *  - `0` (default): every experimental optimisation phase is off
     *  - `1`: Phase 5 — auto-inline single-expression private `defn-`
     *  - `2`: Phase 6 — rewrite self-recursive `defn` tail calls into an
     *    implicit `recur` loop, eliminating per-iteration PHP stack frames
     *    at the cost of a shorter stack trace inside the loop
     *
     * Phases that ship as default-on (e.g. `ConstantFolder`,
     * `LetSimplifier`) do not consult this flag; only phases whose
     * behaviour change is observable across stack traces / profiling
     * output gate themselves here.
     */
    public const int DEFAULT_OPTIMIZATION_LEVEL = 0;

    private string $source = self::DEFAULT_SOURCE;

    private int $startingLine = self::DEFAULT_STARTING_LINE;

    private bool $isEnableSourceMaps = self::DEFAULT_ENABLE_SOURCE_MAPS;

    private bool $emitOnly = self::DEFAULT_EMIT_ONLY;

    private int $optimizationLevel = self::DEFAULT_OPTIMIZATION_LEVEL;

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

    public function getOptimizationLevel(): int
    {
        return $this->optimizationLevel;
    }

    public function setOptimizationLevel(int $level): self
    {
        $this->optimizationLevel = max(0, $level);

        return $this;
    }
}
