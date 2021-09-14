<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter;

class OutputEmitterOptions
{
    public const EMIT_MODE_FILE = 'EMIT_MODE_FILE';
    public const EMIT_MODE_STATEMENT = 'EMIT_MODE_STATEMENT';

    private string $emitMode = self::EMIT_MODE_STATEMENT;

    public function __construct(string $emitMode)
    {
        $this->emitMode = $emitMode;
    }

    public function getEmitMode(): string
    {
        return $this->emitMode;
    }

    public function isFileEmitMode(): bool
    {
        return $this->emitMode === self::EMIT_MODE_FILE;
    }

    public function isStatementEmitMode(): bool
    {
        return $this->emitMode === self::EMIT_MODE_STATEMENT;
    }
}
