<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

enum EmitMode: string
{
    case File = 'EMIT_MODE_FILE';
    case Statement = 'EMIT_MODE_STATEMENT';
    case Cache = 'EMIT_MODE_CACHE';
}
