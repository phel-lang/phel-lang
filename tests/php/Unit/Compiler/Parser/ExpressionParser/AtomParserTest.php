<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Parser\ExpressionParser;

use Phel\Compiler\Application\Lexer;
use Phel\Compiler\Application\Parser;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Parser\Exceptions\KeywordParserException;
use Phel\Compiler\Domain\Parser\Exceptions\NumberParserException;
use Phel\Compiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Domain\Parser\Exceptions\ZeroDenominatorRatioParserException;
use Phel\Compiler\Domain\Parser\ExpressionParser\AtomParser;
use Phel\Compiler\Domain\Parser\ExpressionParserFactory;
use Phel\Lang\BigDecimal;
use Phel\Lang\BigInt;
use Phel\Lang\Keyword;
use Phel\Lang\Ratio;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Parser\Node\BooleanNode;
use Phel\Shared\Parser\Node\KeywordNode;
use Phel\Shared\Parser\Node\NilNode;
use Phel\Shared\Parser\Node\NumberNode;
use Phel\Shared\Parser\Node\SymbolNode;
use Phel\Shared\Parser\Node\Token;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function strlen;

final class AtomParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function oversized_integer_literals(): iterable
    {
        yield '9223372036854775809' => ['9223372036854775809', '9223372036854775809'];
        yield '-9223372036854775809' => ['-9223372036854775809', '-9223372036854775809'];
        yield '0x8000000000000001' => ['0x8000000000000001', '9223372036854775809'];
        yield '-0x8000000000000001' => ['-0x8000000000000001', '-9223372036854775809'];
        yield '0b1000000000000000000000000000000000000000000000000000000000000001' => ['0b1000000000000000000000000000000000000000000000000000000000000001', '9223372036854775809'];
        yield '-0b1000000000000000000000000000000000000000000000000000000000000001' => ['-0b1000000000000000000000000000000000000000000000000000000000000001', '-9223372036854775809'];
        yield '01000000000000000000001' => ['01000000000000000000001', '9223372036854775809'];
        yield '-01000000000000000000001' => ['-01000000000000000000001', '-9223372036854775809'];
        yield '16r8000000000000001' => ['16r8000000000000001', '9223372036854775809'];
        yield '-16r8000000000000001' => ['-16r8000000000000001', '-9223372036854775809'];
        yield '+9_223_372_036_854_775_809' => ['+9_223_372_036_854_775_809', '9223372036854775809'];
        yield '-9_223_372_036_854_775_809' => ['-9_223_372_036_854_775_809', '-9223372036854775809'];
        yield '340282366920938463463374607431768211457' => ['340282366920938463463374607431768211457', '340282366920938463463374607431768211457'];
        yield '-340282366920938463463374607431768211457' => ['-340282366920938463463374607431768211457', '-340282366920938463463374607431768211457'];
        yield '0x100000000000000000000000000000001' => ['0x100000000000000000000000000000001', '340282366920938463463374607431768211457'];
        yield '-0x100000000000000000000000000000001' => ['-0x100000000000000000000000000000001', '-340282366920938463463374607431768211457'];
        yield '0b100000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000001' => ['0b100000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000001', '340282366920938463463374607431768211457'];
        yield '-0b100000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000001' => ['-0b100000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000001', '-340282366920938463463374607431768211457'];
        yield '04000000000000000000000000000000000000000001' => ['04000000000000000000000000000000000000000001', '340282366920938463463374607431768211457'];
        yield '-04000000000000000000000000000000000000000001' => ['-04000000000000000000000000000000000000000001', '-340282366920938463463374607431768211457'];
        yield '16r100000000000000000000000000000001' => ['16r100000000000000000000000000000001', '340282366920938463463374607431768211457'];
        yield '-16r100000000000000000000000000000001' => ['-16r100000000000000000000000000000001', '-340282366920938463463374607431768211457'];
        yield '+340_282_366_920_938_463_463_374_607_431_768_211_457' => ['+340_282_366_920_938_463_463_374_607_431_768_211_457', '340282366920938463463374607431768211457'];
        yield '-340_282_366_920_938_463_463_374_607_431_768_211_457' => ['-340_282_366_920_938_463_463_374_607_431_768_211_457', '-340282366920938463463374607431768211457'];
    }

    #[DataProvider('oversized_integer_literals')]
    public function test_integer_overflow_preserves_exact_value(string $word, string $expected): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $location = new SourceLocation('string', 0, 0);
        $node = $parser->parse(new Token(Token::T_ATOM, $word, $location, $location));

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(BigInt::class, $value);
        self::assertSame($expected, (string) $value);
    }

    public function test_parse_true(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 4);
        $this->assertEquals(
            new BooleanNode(
                'true',
                $start,
                $end,
                true,
            ),
            $parser->parse(
                new Token(Token::T_ATOM, 'true', $start, $end),
            ),
        );
    }

    public function test_parse_false(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $this->assertEquals(
            new BooleanNode(
                'false',
                $start,
                $end,
                false,
            ),
            $parser->parse(
                new Token(Token::T_ATOM, 'false', $start, $end),
            ),
        );
    }

    public function test_parse_nil(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 3);
        $this->assertEquals(
            new NilNode(
                'nil',
                $start,
                $end,
                null,
            ),
            $parser->parse(
                new Token(Token::T_ATOM, 'nil', $start, $end),
            ),
        );
    }

    public function test_parse_keyword_without_namespace(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 4);
        $this->assertEquals(
            new KeywordNode(
                ':foo',
                $start,
                $end,
                Keyword::create('foo'),
            ),
            $parser->parse(
                new Token(Token::T_ATOM, ':foo', $start, $end),
            ),
        );
    }

    public function test_parse_keyword_with_alias(): void
    {
        $env = new GlobalEnvironment();
        $env->addRequireAlias('user', Symbol::create('bar'), Symbol::create('foobar'));

        $parser = new AtomParser($env);
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 9);
        $this->assertEquals(
            new KeywordNode(
                '::bar/foo',
                $start,
                $end,
                Keyword::create('foo', 'foobar'),
            ),
            $parser->parse(
                new Token(Token::T_ATOM, '::bar/foo', $start, $end),
            ),
        );
    }

    public function test_parse_keyword_current_namespace(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('user');

        $parser = new AtomParser($env);
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $this->assertEquals(
            new KeywordNode(
                '::foo',
                $start,
                $end,
                Keyword::create('foo', 'user'),
            ),
            $parser->parse(
                new Token(Token::T_ATOM, '::foo', $start, $end),
            ),
        );
    }

    public function test_parse_keyword_absolute_namespace(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $this->assertEquals(
            new KeywordNode(
                ':xyz\bar/foo',
                $start,
                $end,
                Keyword::create('foo', 'xyz\bar'),
            ),
            $parser->parse(
                new Token(Token::T_ATOM, ':xyz\bar/foo', $start, $end),
            ),
        );
    }

    public function test_parse_keyword_with_slash_in_name(): void
    {
        // Clojure keeps everything after the first `/` as the name, so
        // `:a/b/c` is namespace `a` with name `b/c` (not silently truncated).
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 6);
        $this->assertEquals(
            new KeywordNode(
                ':a/b/c',
                $start,
                $end,
                Keyword::create('b/c', 'a'),
            ),
            $parser->parse(
                new Token(Token::T_ATOM, ':a/b/c', $start, $end),
            ),
        );
    }

    public function test_parse_invalid_keyword(): void
    {
        $this->expectException(KeywordParserException::class);

        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $parser->parse(
            new Token(Token::T_ATOM, ':/', $start, $end),
        );
    }

    public function test_parse_keyword_with_unknown_alias_throws(): void
    {
        $this->expectException(KeywordParserException::class);
        $this->expectExceptionMessage("Can not resolve alias 'missing' in keyword: ::missing/foo");

        $env = new GlobalEnvironment();
        $env->setNs('user');

        $parser = new AtomParser($env);
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 14);
        $parser->parse(
            new Token(Token::T_ATOM, '::missing/foo', $start, $end),
        );
    }

    public function test_parse_binary_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $this->assertEquals(
            new NumberNode(
                '0b001',
                $start,
                $end,
                1,
            ),
            $parser->parse(
                new Token(Token::T_ATOM, '0b001', $start, $end),
            ),
        );
        $this->assertEquals(
            new NumberNode(
                '+0b001',
                $start,
                $end,
                1,
            ),
            $parser->parse(
                new Token(Token::T_ATOM, '+0b001', $start, $end),
            ),
        );
        $this->assertEquals(
            new NumberNode(
                '-0b001',
                $start,
                $end,
                -1,
            ),
            $parser->parse(
                new Token(Token::T_ATOM, '-0b001', $start, $end),
            ),
        );
    }

    public function test_parse_hexadecimal_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $this->assertEquals(
            new NumberNode(
                '0x001',
                $start,
                $end,
                1,
            ),
            $parser->parse(
                new Token(Token::T_ATOM, '0x001', $start, $end),
            ),
        );
    }

    public function test_parse_positive_signed_hexadecimal_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $this->assertEquals(
            new NumberNode(
                '+0x001',
                $start,
                $end,
                1,
            ),
            $parser->parse(
                new Token(Token::T_ATOM, '+0x001', $start, $end),
            ),
        );
    }

    public function test_parse_negative_hexadecimal_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $this->assertEquals(
            new NumberNode(
                '-0x001',
                $start,
                $end,
                -1,
            ),
            $parser->parse(
                new Token(Token::T_ATOM, '-0x001', $start, $end),
            ),
        );
    }

    /**
     * Regression test for https://github.com/phel-lang/phel-lang/issues/1278
     */
    public function test_parse_negative_hexadecimal_php_int_min(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 19);
        $node = $parser->parse(
            new Token(Token::T_ATOM, '-0x8000000000000000', $start, $end),
        );

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(PHP_INT_MIN, $node->getValue());
    }

    public function test_parse_negative_hexadecimal_max_positive_int(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 19);
        $node = $parser->parse(
            new Token(Token::T_ATOM, '-0x7FFFFFFFFFFFFFFF', $start, $end),
        );

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(-PHP_INT_MAX, $node->getValue());
    }

    public function test_parse_negative_hexadecimal_32_bit_min(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 11);
        $node = $parser->parse(
            new Token(Token::T_ATOM, '-0x80000000', $start, $end),
        );

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(-2147483648, $node->getValue());
    }

    /**
     * Regression test for https://github.com/phel-lang/phel-lang/issues/1278
     */
    public function test_parse_negative_binary_php_int_min(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 67);
        $node = $parser->parse(
            new Token(
                Token::T_ATOM,
                '-0b1000000000000000000000000000000000000000000000000000000000000000',
                $start,
                $end,
            ),
        );

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(PHP_INT_MIN, $node->getValue());
    }

    /**
     * Regression test for https://github.com/phel-lang/phel-lang/issues/1278
     */
    public function test_parse_negative_octal_php_int_min(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 24);
        $node = $parser->parse(
            new Token(Token::T_ATOM, '-01000000000000000000000', $start, $end),
        );

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(PHP_INT_MIN, $node->getValue());
    }

    public function test_parse_numeric_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 3);
        $this->assertEquals(
            new NumberNode(
                '1.2',
                $start,
                $end,
                1.2,
            ),
            $parser->parse(
                new Token(Token::T_ATOM, '1.2', $start, $end),
            ),
        );
    }

    public function test_parse_octal_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $this->assertEquals(
            new NumberNode(
                '01',
                $start,
                $end,
                1,
            ),
            $parser->parse(
                new Token(Token::T_ATOM, '01', $start, $end),
            ),
        );
    }

    public function test_parse_positive_signed_octal_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $this->assertEquals(
            new NumberNode(
                '+01',
                $start,
                $end,
                1,
            ),
            $parser->parse(
                new Token(Token::T_ATOM, '+01', $start, $end),
            ),
        );
    }

    public function test_parse_negative_octal_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $this->assertEquals(
            new NumberNode(
                '-01',
                $start,
                $end,
                -1,
            ),
            $parser->parse(
                new Token(Token::T_ATOM, '-01', $start, $end),
            ),
        );
    }

    public function test_parse_binary_radix_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 6);
        $node = $parser->parse(new Token(Token::T_ATOM, '2r1111', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(15, $node->getValue());
    }

    public function test_parse_octal_radix_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $node = $parser->parse(new Token(Token::T_ATOM, '8r777', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(511, $node->getValue());
    }

    public function test_parse_hexadecimal_radix_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $node = $parser->parse(new Token(Token::T_ATOM, '16rFF', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(255, $node->getValue());
    }

    public function test_parse_base_36_radix_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 4);
        $node = $parser->parse(new Token(Token::T_ATOM, '36rZ', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(35, $node->getValue());
    }

    public function test_parse_base_36_double_digit_radix_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $node = $parser->parse(new Token(Token::T_ATOM, '36rZZ', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(1295, $node->getValue());
    }

    public function test_parse_radix_number_past_php_int_max_is_a_bigint(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 17);

        $node = $parser->parse(new Token(Token::T_ATOM, '36rZZZZZZZZZZZZZZ', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertInstanceOf(BigInt::class, $node->getValue());
        self::assertSame('6140942214464815497215', (string) $node->getValue());
    }

    public function test_parse_hexadecimal_past_php_int_max_is_a_bigint(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 18);

        $node = $parser->parse(new Token(Token::T_ATOM, '0xFFFFFFFFFFFFFFFF', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertInstanceOf(BigInt::class, $node->getValue());
        self::assertSame('18446744073709551615', (string) $node->getValue());
    }

    public function test_parse_radix_number_is_case_insensitive(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);

        $lower = $parser->parse(new Token(Token::T_ATOM, '16rff', $start, $end));
        $upper = $parser->parse(new Token(Token::T_ATOM, '16rFF', $start, $end));

        self::assertInstanceOf(NumberNode::class, $lower);
        self::assertInstanceOf(NumberNode::class, $upper);
        self::assertSame(255, $lower->getValue());
        self::assertSame(255, $upper->getValue());
    }

    public function test_parse_uppercase_radix_marker(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 6);
        $node = $parser->parse(new Token(Token::T_ATOM, '2R1111', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(15, $node->getValue());
    }

    public function test_parse_negative_radix_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 7);
        $node = $parser->parse(new Token(Token::T_ATOM, '-2r1111', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(-15, $node->getValue());
    }

    public function test_parse_positive_signed_radix_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 7);
        $node = $parser->parse(new Token(Token::T_ATOM, '+2r1111', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(15, $node->getValue());
    }

    public function test_parse_radix_number_with_underscores(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 11);
        $node = $parser->parse(new Token(Token::T_ATOM, '2r1111_0000', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(240, $node->getValue());
    }

    public function test_parse_radix_number_with_leading_zero(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 6);
        $node = $parser->parse(new Token(Token::T_ATOM, '8r0777', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(511, $node->getValue());
    }

    public function test_invalid_digit_for_base_is_rejected(): void
    {
        $this->expectException(NumberParserException::class);
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 3);
        $parser->parse(new Token(Token::T_ATOM, '2r2', $start, $end));
    }

    public function test_base_greater_than_36_is_rejected(): void
    {
        $this->expectException(NumberParserException::class);
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 4);
        $parser->parse(new Token(Token::T_ATOM, '37r0', $start, $end));
    }

    public function test_parse_bigint_literal(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 2);
        $node = $parser->parse(new Token(Token::T_ATOM, '1N', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(1, $node->getValue());
    }

    public function test_parse_zero_bigint_literal(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 2);
        $node = $parser->parse(new Token(Token::T_ATOM, '0N', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(0, $node->getValue());
    }

    public function test_parse_negative_bigint_literal(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $node = $parser->parse(new Token(Token::T_ATOM, '-123N', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(-123, $node->getValue());
    }

    public function test_parse_positive_signed_bigint_literal(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 5);
        $node = $parser->parse(new Token(Token::T_ATOM, '+123N', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(123, $node->getValue());
    }

    public function test_parse_bigint_literal_with_underscores(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 10);
        $node = $parser->parse(new Token(Token::T_ATOM, '1_000_000N', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(1_000_000, $node->getValue());
    }

    public function test_parse_bigint_literal_above_php_int_max_promotes(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 20);
        $node = $parser->parse(new Token(Token::T_ATOM, '9223372036854775808N', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(BigInt::class, $value);
        self::assertSame('9223372036854775808', (string) $value);
    }

    public function test_parse_bigint_literal_below_php_int_min_promotes(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 21);
        $node = $parser->parse(new Token(Token::T_ATOM, '-9223372036854775809N', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(BigInt::class, $value);
        self::assertSame('-9223372036854775809', (string) $value);
    }

    public function test_parse_bigdec_literal_integer(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 2);
        $node = $parser->parse(new Token(Token::T_ATOM, '1M', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(BigDecimal::class, $value);
        self::assertSame('1', (string) $value);
    }

    public function test_parse_bigdec_literal_decimal(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 4);
        $node = $parser->parse(new Token(Token::T_ATOM, '1.5M', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(BigDecimal::class, $value);
        self::assertSame('1.5', (string) $value);
    }

    public function test_parse_negative_bigdec_literal(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 9);
        $node = $parser->parse(new Token(Token::T_ATOM, '-123.456M', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(BigDecimal::class, $value);
        self::assertSame('-123.456', (string) $value);
    }

    public function test_parse_zero_bigdec_literal(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 2);
        $node = $parser->parse(new Token(Token::T_ATOM, '0M', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(BigDecimal::class, $value);
        self::assertSame('0', (string) $value);
    }

    public function test_parse_bigdec_literal_with_exponent(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 6);
        $node = $parser->parse(new Token(Token::T_ATOM, '1.5e3M', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(BigDecimal::class, $value);
        self::assertSame('1500', (string) $value);
    }

    public function test_parse_bigdec_literal_with_underscores(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 10);
        $node = $parser->parse(new Token(Token::T_ATOM, '1_000.5_5M', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(BigDecimal::class, $value);
        self::assertSame('1000.55', (string) $value);
    }

    public function test_symbol_with_trailing_n_is_not_bigint(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 4);
        $node = $parser->parse(new Token(Token::T_ATOM, 'foo1N', $start, $end));

        self::assertInstanceOf(SymbolNode::class, $node);
    }

    public function test_symbol_with_trailing_m_is_not_bigdec(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 4);
        $node = $parser->parse(new Token(Token::T_ATOM, 'foo1M', $start, $end));

        self::assertInstanceOf(SymbolNode::class, $node);
    }

    #[DataProvider('malformed_numeric_tokens')]
    public function test_rejects_malformed_numeric_tokens(string $word): void
    {
        $this->expectException(NumberParserException::class);
        $this->expectExceptionMessage('Invalid number: ' . $word);
        $parser = new AtomParser(new GlobalEnvironment());
        $parser->parse(new Token(Token::T_ATOM, $word, new SourceLocation('invalid.phel', 2, 3), new SourceLocation('invalid.phel', 2, 3 + strlen($word))));
    }

    public function test_invalid_numeric_token_keeps_its_source_location(): void
    {
        $parser = new Parser(new ExpressionParserFactory(), new GlobalEnvironment());
        try {
            $parser->parseAll(new Lexer()->lexString("\n   1+", 'invalid.phel'));
            self::fail('Expected an invalid-number error');
        } catch (UnexpectedParserException $unexpectedParserException) {
            self::assertSame('Invalid number: 1+', $unexpectedParserException->getMessage());
            self::assertEquals(new SourceLocation('invalid.phel', 2, 3), $unexpectedParserException->getStartLocation());
            self::assertEquals(new SourceLocation('invalid.phel', 2, 5), $unexpectedParserException->getEndLocation());
        }
    }

    public static function malformed_numeric_tokens(): iterable
    {
        foreach (['1+', '1_', '12_', '-1+', '+12_', '1__2', '1e+', '1abc', '0xG', '0b2', '2r2', '37r0'] as $word) {
            yield $word => [$word];
        }
    }

    #[DataProvider('valid_decimal_forms')]
    public function test_preserves_valid_decimal_forms(string $word, int|float $expected): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $node = $parser->parse(new Token(Token::T_ATOM, $word, new SourceLocation('string', 1, 0), new SourceLocation('string', 1, strlen($word))));
        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame($expected, $node->getValue());
    }

    public static function valid_decimal_forms(): iterable
    {
        yield 'grouped integer' => ['1_000', 1000];
        yield 'negative grouped integer' => ['-1_000', -1000];
        yield 'positive grouped integer' => ['+1_000', 1000];
        yield 'trailing decimal point' => ['1.', 1.0];
        yield 'leading decimal point' => ['.5', 0.5];
        yield 'signed leading decimal point' => ['-.5', -0.5];
        yield 'negative exponent' => ['1e-2', 0.01];
        yield 'positive exponent' => ['1e+2', 100.0];
        yield 'signed exponent float' => ['+1.5e+2', 150.0];
    }

    public function test_preserves_arithmetic_operators_and_nonnumeric_sign_prefixes(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        foreach (['+', '-', '/', '+name', '-name', '.member', '/a'] as $word) {
            $node = $parser->parse(new Token(Token::T_ATOM, $word, new SourceLocation('string', 1, 0), new SourceLocation('string', 1, strlen($word))));
            self::assertInstanceOf(SymbolNode::class, $node);
            self::assertSame($word, $node->getValue()->getFullName());
        }
    }

    public function test_parse_symbol(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 1);
        $this->assertEquals(
            new SymbolNode(
                'x',
                $start,
                $end,
                Symbol::create('x'),
            ),
            $parser->parse(
                new Token(Token::T_ATOM, 'x', $start, $end),
            ),
        );
    }

    /**
     * Regression test for https://github.com/phel-lang/phel-lang/issues/616
     */
    public function test_parse_symbol_ending_in_zero(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 2);
        $this->assertEquals(
            new SymbolNode(
                'x0',
                $start,
                $end,
                Symbol::create('x0'),
            ),
            $parser->parse(
                new Token(Token::T_ATOM, 'x0', $start, $end),
            ),
        );
    }

    public function test_parse_ratio_literal_returns_rational(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 3);
        $node = $parser->parse(new Token(Token::T_ATOM, '1/2', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(Ratio::class, $value);
        self::assertSame('1', (string) $value->numerator());
        self::assertSame('2', (string) $value->denominator());
    }

    public function test_parse_negative_ratio_literal_returns_rational(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 4);
        $node = $parser->parse(new Token(Token::T_ATOM, '-3/4', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(Ratio::class, $value);
        self::assertSame('-3', (string) $value->numerator());
        self::assertSame('4', (string) $value->denominator());
    }

    public function test_parse_ratio_literal_normalises_to_int_when_integral(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 3);
        $node = $parser->parse(new Token(Token::T_ATOM, '4/2', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(2, $node->getValue());
    }

    public function test_parse_ratio_literal_zero_numerator_returns_int_zero(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 3);
        $node = $parser->parse(new Token(Token::T_ATOM, '0/5', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(0, $node->getValue());
    }

    public function test_parse_ratio_literal_with_underscores(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 11);
        $node = $parser->parse(new Token(Token::T_ATOM, '1_000/3_000', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(Ratio::class, $value);
        self::assertSame('1', (string) $value->numerator());
        self::assertSame('3', (string) $value->denominator());
    }

    public function test_parse_ratio_literal_with_huge_integral_value_returns_big_integer(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 23);
        $node = $parser->parse(
            new Token(Token::T_ATOM, '20000000000000000000/2', $start, $end),
        );

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(BigInt::class, $value);
        self::assertSame('10000000000000000000', (string) $value);
    }

    public function test_parse_ratio_literal_zero_denominator_throws(): void
    {
        $this->expectException(ZeroDenominatorRatioParserException::class);
        $this->expectExceptionMessage('Invalid ratio 1/0: the denominator is zero');

        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 3);
        $parser->parse(new Token(Token::T_ATOM, '1/0', $start, $end));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLeadingZeroWithNonOctalDigit(): iterable
    {
        yield '08' => ['08'];
        yield '09' => ['09'];
        yield 'negative' => ['-08'];
        yield 'positive' => ['+09'];
        yield 'longer' => ['0128'];
        yield 'bigint suffix' => ['09N'];
    }

    #[DataProvider('providerLeadingZeroWithNonOctalDigit')]
    public function test_parse_leading_zero_with_a_non_octal_digit_throws(string $word): void
    {
        $this->expectException(NumberParserException::class);
        $this->expectExceptionMessage('Invalid number: ' . $word);

        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, strlen($word));
        $parser->parse(new Token(Token::T_ATOM, $word, $start, $end));
    }

    /**
     * @return iterable<string, array{string, float|int}>
     */
    public static function providerLeadingZeroThatStillReads(): iterable
    {
        yield 'octal' => ['017', 15];
        yield 'zero' => ['0', 0];
        yield 'float' => ['08.5', 8.5];
        yield 'exponent' => ['09e1', 90.0];
    }

    #[DataProvider('providerLeadingZeroThatStillReads')]
    public function test_parse_leading_zero_that_still_reads(string $word, float|int $expected): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, strlen($word));

        self::assertSame($expected, $parser->parse(new Token(Token::T_ATOM, $word, $start, $end))->getValue());
    }

    public function test_parse_ratio_literal_zero_over_zero_throws(): void
    {
        $this->expectException(ZeroDenominatorRatioParserException::class);
        $this->expectExceptionMessage('Invalid ratio 0/0: the denominator is zero');

        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 3);
        $parser->parse(new Token(Token::T_ATOM, '0/0', $start, $end));
    }

    public function test_parse_oversize_decimal_int_literal_returns_bigint(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 20);
        $node = $parser->parse(
            new Token(Token::T_ATOM, '99999999999999999999', $start, $end),
        );

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertInstanceOf(BigInt::class, $value);
        self::assertSame('99999999999999999999', (string) $value);
    }

    public function test_parse_oversize_negative_decimal_int_literal_returns_bigint(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 21);
        $node = $parser->parse(
            new Token(Token::T_ATOM, '-99999999999999999999', $start, $end),
        );

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertInstanceOf(BigInt::class, $node->getValue());
        self::assertSame('-99999999999999999999', (string) $node->getValue());
    }

    public function test_parse_php_int_max_decimal_literal_stays_int(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $word = (string) PHP_INT_MAX;
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, strlen($word));
        $node = $parser->parse(
            new Token(Token::T_ATOM, $word, $start, $end),
        );

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(PHP_INT_MAX, $node->getValue());
    }

    public function test_parse_leading_dot_float_stays_number(): void
    {
        // `.5` is not matched by the anchored decimal regex but `is_numeric`
        // accepts it; the first-char guard must keep `.` in the numeric set.
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 2);
        $node = $parser->parse(new Token(Token::T_ATOM, '.5', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(0.5, $node->getValue());
    }

    public function test_parse_signed_decimal_stays_number(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 2);
        $node = $parser->parse(new Token(Token::T_ATOM, '+5', $start, $end));

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertSame(5, $node->getValue());
    }

    public function test_parse_alphabetic_word_is_symbol(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 3);
        $node = $parser->parse(new Token(Token::T_ATOM, 'map', $start, $end));

        self::assertInstanceOf(SymbolNode::class, $node);
        self::assertSame('map', $node->getValue()->getName());
    }

    public function test_parse_sign_only_word_is_symbol(): void
    {
        // `+` enters the numeric branch (sign first char) but matches no
        // numeric form, so it falls through to a symbol — unchanged.
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 1);
        $node = $parser->parse(new Token(Token::T_ATOM, '+', $start, $end));

        self::assertInstanceOf(SymbolNode::class, $node);
        self::assertSame('+', $node->getValue()->getName());
    }
}
