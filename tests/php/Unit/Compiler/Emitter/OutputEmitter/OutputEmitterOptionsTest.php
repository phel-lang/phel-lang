<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Emitter\OutputEmitter\OutputEmitterOptions;
use PHPUnit\Framework\TestCase;

final class OutputEmitterOptionsTest extends TestCase
{
    public function test_default_mode_is_statement(): void
    {
        $options = new OutputEmitterOptions();

        self::assertTrue($options->isStatementEmitMode());
        self::assertFalse($options->isFileEmitMode());
        self::assertFalse($options->isCacheEmitMode());
    }

    public function test_is_file_emit_mode(): void
    {
        $options = new OutputEmitterOptions(OutputEmitterOptions::EMIT_MODE_FILE);

        self::assertTrue($options->isFileEmitMode());
        self::assertFalse($options->isStatementEmitMode());
        self::assertFalse($options->isCacheEmitMode());
    }

    public function test_is_statement_emit_mode(): void
    {
        $options = new OutputEmitterOptions(OutputEmitterOptions::EMIT_MODE_STATEMENT);

        self::assertTrue($options->isStatementEmitMode());
        self::assertFalse($options->isFileEmitMode());
        self::assertFalse($options->isCacheEmitMode());
    }

    public function test_is_cache_emit_mode(): void
    {
        $options = new OutputEmitterOptions(OutputEmitterOptions::EMIT_MODE_CACHE);

        self::assertTrue($options->isCacheEmitMode());
        self::assertFalse($options->isStatementEmitMode());
        self::assertFalse($options->isFileEmitMode());
    }
}
