<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Exceptions\Hint;

use Error;
use Phel\Shared\Exceptions\Hint\UndefinedSymbolHint;
use PHPUnit\Framework\TestCase;

final class UndefinedSymbolHintTest extends TestCase
{
    public function test_extracts_phel_resolve_symbol(): void
    {
        $hint = new UndefinedSymbolHint();
        $e = new Error("Cannot resolve symbol 'frobnicate'");

        self::assertTrue($hint->appliesTo($e));
        self::assertStringContainsString("'frobnicate'", $hint->hint($e));
        self::assertStringContainsString(':require', $hint->hint($e));
    }

    public function test_extracts_undefined_function(): void
    {
        $hint = new UndefinedSymbolHint();
        $e = new Error('Call to undefined function App\\does_not_exist()');

        self::assertTrue($hint->appliesTo($e));
        self::assertStringContainsString('does_not_exist', $hint->hint($e));
    }

    public function test_does_not_apply_when_message_unrelated(): void
    {
        self::assertFalse(new UndefinedSymbolHint()->appliesTo(new Error('different problem')));
    }
}
