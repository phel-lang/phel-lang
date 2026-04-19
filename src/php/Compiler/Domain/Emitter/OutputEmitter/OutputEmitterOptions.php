<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

final readonly class OutputEmitterOptions
{
    public function __construct(
        private EmitMode $emitMode = EmitMode::Statement,
    ) {}

    public function isFileEmitMode(): bool
    {
        return $this->emitMode === EmitMode::File;
    }

    public function isStatementEmitMode(): bool
    {
        return $this->emitMode === EmitMode::Statement;
    }

    public function isCacheEmitMode(): bool
    {
        return $this->emitMode === EmitMode::Cache;
    }
}
