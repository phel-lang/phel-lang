<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Emitter\OutputEmitter\PhpStringEscape;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class PhpStringEscapeTest extends TestCase
{
    public function test_apostrophe_passes_through_unchanged(): void
    {
        // `addslashes` would escape `'` to `\'`, which a PHP double-quoted
        // literal keeps verbatim and would silently mangle `inc'`/`dec'`/etc.
        self::assertSame("inc'", PhpStringEscape::doubleQuoted("inc'"));
        self::assertSame("+'", PhpStringEscape::doubleQuoted("+'"));
        self::assertSame("foo''", PhpStringEscape::doubleQuoted("foo''"));
    }

    public function test_backslash_is_doubled(): void
    {
        self::assertSame('a\\\\b', PhpStringEscape::doubleQuoted('a\\b'));
    }

    public function test_double_quote_is_escaped(): void
    {
        self::assertSame('a\\"b', PhpStringEscape::doubleQuoted('a"b'));
    }

    public function test_dollar_is_escaped_to_block_interpolation(): void
    {
        self::assertSame('a\\$var', PhpStringEscape::doubleQuoted('a$var'));
    }

    public function test_round_trips_into_a_double_quoted_php_literal(): void
    {
        $names = ["inc'", "dec'", "+'", "-'", "*'", "foo''", 'plain', 'with"quote', 'with$dollar', 'with\\backslash'];

        foreach ($names as $name) {
            $literal = '"' . PhpStringEscape::doubleQuoted($name) . '"';
            /** @var mixed $value */
            $value = eval('return ' . $literal . ';');
            self::assertSame($name, $value, sprintf('round-trip for %s', $name));
        }
    }
}
