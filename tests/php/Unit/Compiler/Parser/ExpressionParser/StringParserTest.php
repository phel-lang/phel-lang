<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Parser\ExpressionParser;

use Phel\Compiler\Domain\Parser\Exceptions\StringParserException;
use Phel\Compiler\Domain\Parser\ExpressionParser\StringParser;
use Phel\Lang\SourceLocation;
use Phel\Shared\Parser\Node\StringNode;
use Phel\Shared\Parser\Node\Token;
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
        $this->expectExceptionMessageIsOrContains('Octal escape sequence out of range: \400 is above \377.');

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

    private function parse(string $raw): StringNode
    {
        $parser = new StringParser();
        $start = new SourceLocation('string', 1, 0);
        $end = new SourceLocation('string', 1, strlen($raw));

        return $parser->parse(new Token(Token::T_STRING, $raw, $start, $end));
    }
}
