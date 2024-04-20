<?php

declare(strict_types=1);

namespace PhelTest\Unit\Transpiler\Parser\ExpressionParser;

use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Transpiler\Domain\Lexer\Token;
use Phel\Transpiler\Domain\Parser\Exceptions\KeywordParserException;
use Phel\Transpiler\Domain\Parser\ExpressionParser\AtomParser;
use Phel\Transpiler\Domain\Parser\ParserNode\BooleanNode;
use Phel\Transpiler\Domain\Parser\ParserNode\KeywordNode;
use Phel\Transpiler\Domain\Parser\ParserNode\NilNode;
use Phel\Transpiler\Domain\Parser\ParserNode\NumberNode;
use Phel\Transpiler\Domain\Parser\ParserNode\SymbolNode;
use PHPUnit\Framework\TestCase;

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
                Keyword::createForNamespace('foobar', 'foo'),
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
                Keyword::createForNamespace('user', 'foo'),
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
                Keyword::createForNamespace('xyz\bar', 'foo'),
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
}
