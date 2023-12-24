<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

final readonly class OutputEmitterOptions
{
    public const EMIT_MODE_FILE = 'EMIT_MODE_FILE';

    public const EMIT_MODE_STATEMENT = 'EMIT_MODE_STATEMENT';

    public function __construct(
        private string $emitMode = self::EMIT_MODE_STATEMENT,
    ) {
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
