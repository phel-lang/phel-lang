<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Parser\ExpressionParser;

use Phel\Compiler\Domain\Parser\Exceptions\StringParserException;
use Phel\Compiler\Domain\Parser\ExpressionParser\StringParser;
use Phel\Lang\SourceLocation;
use Phel\Shared\Parser\Node\StringNode;
use Phel\Shared\Parser\Node\Token;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function strlen;

final class StringParserTest extends TestCase
{
    public function test_parse_octal_escape(): void
    {
        self::assertSame('A', $this->parse('"\101"')->getValue());
    }

    public function test_parse_octal_highest_byte(): void
    {
        self::assertSame("\xFF", $this->parse('"\377"')->getValue());
    }

    public function test_parse_octal_above_a_byte_is_rejected(): void
    {
        $this->expectException(StringParserException::class);
        $this->expectExceptionMessage('Octal escape sequence out of range: \400 is above \377.');

        $this->parse('"\400"');
    }

    public function test_parse_octal_max_three_digits_is_rejected(): void
    {
        // 0777 is 511. chr() used to wrap it to 255, so "\777" silently became "\377".
        $this->expectException(StringParserException::class);

        $this->parse('"\777"');
    }

    public function test_parse_hex_escape_highest_byte(): void
    {
        self::assertSame("\xFF", $this->parse('"\xFF"')->getValue());
    }

    public function test_parse_unicode_escape_is_unaffected(): void
    {
        self::assertSame('€', $this->parse('"€"')->getValue());
    }

    public function test_parse_braced_unicode_escape_is_unaffected(): void
    {
        self::assertSame('😀', $this->parse('"\u{1F600}"')->getValue());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid_unicode_escapes(): iterable
    {
        foreach (['\uDC00\uD800', '\uD800x\uDC00', '\uD800\u{DC00}', '\u{D800}\uDC00', '\u0041\uDC00', '\uD800\\\\uDC00', '\\\\uD800\uDC00', '\u{110000}', '\u{1FFFFF}', '\u{10000000000000000}', '\u{FFFFFFFFFFFFFFFFFFFFFFFF}', '\uD800', '\uDBFF', '\uDC00', '\uDFFF', '\u{D800}', '\u{DFFF}'] as $escape) {
            yield $escape => [$escape];
        }
    }

    #[DataProvider('invalid_unicode_escapes')]
    public function test_invalid_unicode_scalar_escape_is_rejected(string $escape): void
    {
        $this->expectException(StringParserException::class);
        $this->expectExceptionMessage('Invalid UTF-8 codepoint escape sequence');

        $this->parse('"' . $escape . '"');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function valid_unicode_escapes(): iterable
    {
        foreach ([0, 0x7F, 0x80, 0x7FF, 0x800, 0xD7FF, 0xE000, 0xFFFF, 0x10000, 0x10FFFF] as $codepoint) {
            $escape = '\u{' . dechex($codepoint) . '}';
            yield $escape => [$escape, mb_chr($codepoint, 'UTF-8')];
        }
        yield 'leading zeroes' => ['\u{000000000000000000000000000000000041}', 'A'];
        yield 'only zeroes' => ['\u{000000000000000000000000000000000000}', "\0"];
    }

    #[DataProvider('valid_unicode_escapes')]
    public function test_valid_unicode_scalar_escape_is_exact(string $escape, string $expected): void
    {
        self::assertSame($expected, $this->parse('"' . $escape . '"')->getValue());
    }

    public function test_binary_source_and_escaped_backslashes_are_preserved(): void
    {
        self::assertSame("\xFF", $this->parse('"' . "\xFF" . '"')->getValue());
        self::assertSame('\uD800', $this->parse('"\\\\uD800"')->getValue());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function surrogate_pairs(): iterable
    {
        yield 'minimum' => ['\uD800\uDC00', "\u{10000}"];
        yield 'emoji' => ['\uD83D\uDE00', '😀'];
        yield 'maximum' => ['\uDBFF\uDFFF', "\u{10FFFF}"];
        yield 'mixed case' => ['\ud83D\udE00', '😀'];
    }

    #[DataProvider('surrogate_pairs')]
    public function test_adjacent_fixed_width_surrogate_pair_is_combined(string $escape, string $expected): void
    {
        self::assertSame($expected . 'A', $this->parse('"' . $escape . '\u0041"')->getValue());
    }

    public function test_ordinary_and_escaped_unicode_sequences_are_not_paired(): void
    {
        self::assertSame('AB', $this->parse('"\u0041\u0042"')->getValue());
        self::assertSame('\uD800\uDC00', $this->parse('"\\\\uD800\\\\uDC00"')->getValue());
    }

    private function parse(string $raw): StringNode
    {
        $parser = new StringParser();
        $start = new SourceLocation('string', 1, 0);
        $end = new SourceLocation('string', 1, strlen($raw));

        return $parser->parse(new Token(Token::T_STRING, $raw, $start, $end));
    }
}
