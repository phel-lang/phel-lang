<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Parser\ExpressionParser;

use Phel\Compiler\Domain\Parser\Exceptions\StringParserException;
use Phel\Compiler\Domain\Parser\ExpressionParser\CharParser;
use Phel\Lang\SourceLocation;
use Phel\Shared\Parser\Node\StringNode;
use Phel\Shared\Parser\Node\Token;
use PHPUnit\Framework\TestCase;

use function strlen;

final class CharParserTest extends TestCase
{
    public function test_parse_single_alpha(): void
    {
        $node = $this->parse('\\a');

        self::assertSame('\\a', $node->getCode());
        self::assertSame('a', $node->getValue());
    }

    public function test_parse_single_digit(): void
    {
        $node = $this->parse('\\1');

        self::assertSame('1', $node->getValue());
    }

    public function test_parse_single_uppercase(): void
    {
        $node = $this->parse('\\Z');

        self::assertSame('Z', $node->getValue());
    }

    public function test_parse_named_space(): void
    {
        self::assertSame(' ', $this->parse('\\space')->getValue());
    }

    public function test_parse_named_newline(): void
    {
        self::assertSame("\n", $this->parse('\\newline')->getValue());
    }

    public function test_parse_named_tab(): void
    {
        self::assertSame("\t", $this->parse('\\tab')->getValue());
    }

    public function test_parse_named_formfeed(): void
    {
        self::assertSame("\f", $this->parse('\\formfeed')->getValue());
    }

    public function test_parse_named_backspace(): void
    {
        self::assertSame("\x08", $this->parse('\\backspace')->getValue());
    }

    public function test_parse_named_return(): void
    {
        self::assertSame("\r", $this->parse('\\return')->getValue());
    }

    public function test_parse_unicode_lowercase_hex(): void
    {
        // \u03a9 is the Greek capital letter Omega (Ω), 2 bytes in UTF-8
        $node = $this->parse('\\u03a9');

        self::assertSame("\xCE\xA9", $node->getValue());
    }

    public function test_parse_unicode_uppercase_hex(): void
    {
        $node = $this->parse('\\u03A9');

        self::assertSame("\xCE\xA9", $node->getValue());
    }

    public function test_parse_octal_capital_a(): void
    {
        // 0o101 = 65 = 'A'
        $node = $this->parse('\\o101');

        self::assertSame('A', $node->getValue());
    }

    public function test_parse_octal_capital_s(): void
    {
        // 0o123 = 83 = 'S'
        $node = $this->parse('\\o123');

        self::assertSame('S', $node->getValue());
    }

    public function test_parse_paren(): void
    {
        self::assertSame('(', $this->parse('\\(')->getValue());
    }

    public function test_parse_bracket(): void
    {
        self::assertSame('[', $this->parse('\\[')->getValue());
    }

    public function test_parse_brace(): void
    {
        self::assertSame('{', $this->parse('\\{')->getValue());
    }

    public function test_parse_backslash(): void
    {
        self::assertSame('\\', $this->parse('\\\\')->getValue());
    }

    public function test_parse_double_quote(): void
    {
        self::assertSame('"', $this->parse('\\"')->getValue());
    }

    public function test_parse_comma(): void
    {
        self::assertSame(',', $this->parse('\\,')->getValue());
    }

    public function test_parse_semicolon(): void
    {
        self::assertSame(';', $this->parse('\\;')->getValue());
    }

    public function test_parse_octal_highest_byte(): void
    {
        self::assertSame("\xFF", $this->parse('\\o377')->getValue());
    }

    public function test_parse_octal_above_a_byte_is_rejected(): void
    {
        $this->expectException(StringParserException::class);
        $this->expectExceptionMessage('Octal escape sequence out of range: \\o400 is above \\o377.');

        $this->parse('\\o400');
    }

    public function test_parse_octal_max_three_digits_is_rejected(): void
    {
        // 0777 is 511. chr() used to wrap it to 255, silently yielding \o377.
        $this->expectException(StringParserException::class);

        $this->parse('\\o777');
    }

    private function parse(string $raw): StringNode
    {
        $parser = new CharParser();
        $start = new SourceLocation('string', 1, 0);
        $end = new SourceLocation('string', 1, strlen($raw));

        return $parser->parse(new Token(Token::T_CHAR, $raw, $start, $end));
    }
}
