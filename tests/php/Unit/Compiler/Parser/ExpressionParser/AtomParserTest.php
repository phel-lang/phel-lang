<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Parser\ExpressionParser;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\Exceptions\KeywordParserException;
use Phel\Compiler\Domain\Parser\Exceptions\ZeroDenominatorRatioParserException;
use Phel\Compiler\Domain\Parser\ExpressionParser\AtomParser;
use Phel\Compiler\Domain\Parser\ParserNode\BooleanNode;
use Phel\Compiler\Domain\Parser\ParserNode\KeywordNode;
use Phel\Compiler\Domain\Parser\ParserNode\NilNode;
use Phel\Compiler\Domain\Parser\ParserNode\NumberNode;
use Phel\Compiler\Domain\Parser\ParserNode\SymbolNode;
use Phel\Lang\BigDecimal;
use Phel\Lang\BigInteger;
use Phel\Lang\Keyword;
use Phel\Lang\Rational;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

use function strlen;

final class AtomParserTest extends TestCase
{
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

    public function test_invalid_digit_for_base_falls_through_to_symbol(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 3);
        $node = $parser->parse(new Token(Token::T_ATOM, '2r2', $start, $end));

        self::assertInstanceOf(SymbolNode::class, $node);
    }

    public function test_base_greater_than_36_not_matched(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 4);
        $node = $parser->parse(new Token(Token::T_ATOM, '37r0', $start, $end));

        self::assertInstanceOf(SymbolNode::class, $node);
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
        self::assertInstanceOf(BigInteger::class, $value);
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
        self::assertInstanceOf(BigInteger::class, $value);
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
        self::assertInstanceOf(Rational::class, $value);
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
        self::assertInstanceOf(Rational::class, $value);
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
        self::assertInstanceOf(Rational::class, $value);
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
        self::assertInstanceOf(BigInteger::class, $value);
        self::assertSame('10000000000000000000', (string) $value);
    }

    public function test_parse_ratio_literal_zero_denominator_throws(): void
    {
        $this->expectException(ZeroDenominatorRatioParserException::class);
        $this->expectExceptionMessage('Ratio literal denominator cannot be zero: 1/0');

        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 3);
        $parser->parse(new Token(Token::T_ATOM, '1/0', $start, $end));
    }

    public function test_parse_ratio_literal_zero_over_zero_throws(): void
    {
        $this->expectException(ZeroDenominatorRatioParserException::class);
        $this->expectExceptionMessage('Ratio literal denominator cannot be zero: 0/0');

        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 3);
        $parser->parse(new Token(Token::T_ATOM, '0/0', $start, $end));
    }

    public function test_parse_oversize_decimal_int_literal_returns_float(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 20);
        $node = $parser->parse(
            new Token(Token::T_ATOM, '99999999999999999999', $start, $end),
        );

        self::assertInstanceOf(NumberNode::class, $node);
        $value = $node->getValue();
        self::assertIsFloat($value);
        self::assertSame((float) '99999999999999999999', $value);
    }

    public function test_parse_oversize_negative_decimal_int_literal_returns_float(): void
    {
        $parser = new AtomParser(new GlobalEnvironment());
        $start = new SourceLocation('string', 0, 0);
        $end = new SourceLocation('string', 0, 21);
        $node = $parser->parse(
            new Token(Token::T_ATOM, '-99999999999999999999', $start, $end),
        );

        self::assertInstanceOf(NumberNode::class, $node);
        self::assertIsFloat($node->getValue());
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
}
