<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Parser;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentSingleton;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Lexer\Token;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Parser\ParserNode\BooleanNode;
use Phel\Compiler\Parser\ParserNode\CommentNode;
use Phel\Compiler\Parser\ParserNode\KeywordNode;
use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\MetaNode;
use Phel\Compiler\Parser\ParserNode\NewlineNode;
use Phel\Compiler\Parser\ParserNode\NilNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ParserNode\NumberNode;
use Phel\Compiler\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Parser\ParserNode\StringNode;
use Phel\Compiler\Parser\ParserNode\SymbolNode;
use Phel\Compiler\Parser\ParserNode\WhitespaceNode;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    private CompilerFacadeInterface $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        GlobalEnvironmentSingleton::reset();
    }

    public function setUp(): void
    {
        Symbol::resetGen();
        $this->compilerFacade = new CompilerFacade();
    }

    public function test_read_number(): void
    {
        self::assertEquals(new NumberNode('1', $this->loc(1, 0), $this->loc(1, 1), 1), $this->parse('1'));
        self::assertEquals(new NumberNode('10', $this->loc(1, 0), $this->loc(1, 2), 10), $this->parse('10'));
        self::assertEquals(new NumberNode('100', $this->loc(1, 0), $this->loc(1, 3), 100), $this->parse('100'));
        self::assertEquals(new NumberNode('10.0', $this->loc(1, 0), $this->loc(1, 4), 10.0), $this->parse('10.0'));
        self::assertEquals(new NumberNode('1.1', $this->loc(1, 0), $this->loc(1, 3), 1.1), $this->parse('1.1'));
        self::assertEquals(new NumberNode('10.11', $this->loc(1, 0), $this->loc(1, 5), 10.11), $this->parse('10.11'));
        self::assertEquals(new NumberNode('0x539', $this->loc(1, 0), $this->loc(1, 5), 1337), $this->parse('0x539'));
        self::assertEquals(new NumberNode('0x5_3_9', $this->loc(1, 0), $this->loc(1, 7), 1337), $this->parse('0x5_3_9'));
        self::assertEquals(new NumberNode('02471', $this->loc(1, 0), $this->loc(1, 5), 1337), $this->parse('02471'));
        self::assertEquals(new NumberNode('024_71', $this->loc(1, 0), $this->loc(1, 6), 1337), $this->parse('024_71'));
        self::assertEquals(new NumberNode('0b10100111001', $this->loc(1, 0), $this->loc(1, 13), 1337), $this->parse('0b10100111001'));
        self::assertEquals(new NumberNode('0b0101_0011_1001', $this->loc(1, 0), $this->loc(1, 16), 1337), $this->parse('0b0101_0011_1001'));
        self::assertEquals(new NumberNode('1337e0', $this->loc(1, 0), $this->loc(1, 6), 1337), $this->parse('1337e0'));
        self::assertEquals(new NumberNode('-1337', $this->loc(1, 0), $this->loc(1, 5), -1337), $this->parse('-1337'));
        self::assertEquals(new NumberNode('-1337.0', $this->loc(1, 0), $this->loc(1, 7), -1337.0), $this->parse('-1337.0'));
        self::assertEquals(new NumberNode('+1337', $this->loc(1, 0), $this->loc(1, 5), 1337), $this->parse('+1337'));
        self::assertEquals(new NumberNode('+1337.0', $this->loc(1, 0), $this->loc(1, 7), 1337), $this->parse('+1337.0'));
        self::assertEquals(new NumberNode('1.2e3', $this->loc(1, 0), $this->loc(1, 5), 1.2e3), $this->parse('1.2e3'));
        self::assertEquals(new NumberNode('7E-10', $this->loc(1, 0), $this->loc(1, 5), 7E-10), $this->parse('7E-10'));
    }

    public function test_read_keyword(): void
    {
        self::assertEquals(
            new KeywordNode(':test', $this->loc(1, 0), $this->loc(1, 5), Keyword::create('test')),
            $this->parse(':test')
        );
    }

    public function test_read_boolean(): void
    {
        self::assertEquals(
            new BooleanNode('true', $this->loc(1, 0), $this->loc(1, 4), true),
            $this->parse('true')
        );
        self::assertEquals(
            new BooleanNode('false', $this->loc(1, 0), $this->loc(1, 5), false),
            $this->parse('false')
        );
    }

    public function test_read_nil(): void
    {
        self::assertEquals(
            new NilNode('nil', $this->loc(1, 0), $this->loc(1, 3), null),
            $this->parse('nil')
        );
    }

    public function test_read_symbol(): void
    {
        self::assertEquals(
            new SymbolNode('test', $this->loc(1, 0), $this->loc(1, 4), Symbol::create('test')),
            $this->parse('test')
        );
    }

    public function test_read_list(): void
    {
        self::assertEquals(
            new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 2), []),
            $this->parse('()')
        );
        self::assertEquals(
            new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 4), [
                new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 1), $this->loc(1, 3), []),
            ]),
            $this->parse('(())')
        );

        self::assertEquals(
            new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 3), [
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            ]),
            $this->parse('(a)')
        );

        self::assertEquals(
            new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 5), [
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
                new WhitespaceNode(' ', $this->loc(1, 2), $this->loc(1, 3)),
                new SymbolNode('b', $this->loc(1, 3), $this->loc(1, 4), Symbol::create('b')),
            ]),
            $this->parse('(a b)')
        );
    }

    public function test_rdlist_bracket(): void
    {
        self::assertEquals(
            new ListNode(Token::T_OPEN_BRACKET, $this->loc(1, 0), $this->loc(1, 2), []),
            $this->parse('[]')
        );
        self::assertEquals(
            new ListNode(Token::T_OPEN_BRACKET, $this->loc(1, 0), $this->loc(1, 4), [
                new ListNode(Token::T_OPEN_BRACKET, $this->loc(1, 1), $this->loc(1, 3), []),
            ]),
            $this->parse('[[]]')
        );

        self::assertEquals(
            new ListNode(Token::T_OPEN_BRACKET, $this->loc(1, 0), $this->loc(1, 3), [
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            ]),
            $this->parse('[a]')
        );

        self::assertEquals(
            new ListNode(Token::T_OPEN_BRACKET, $this->loc(1, 0), $this->loc(1, 5), [
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
                new WhitespaceNode(' ', $this->loc(1, 2), $this->loc(1, 3)),
                new SymbolNode('b', $this->loc(1, 3), $this->loc(1, 4), Symbol::create('b')),
            ]),
            $this->parse('[a b]')
        );
    }

    public function test_quote(): void
    {
        self::assertEquals(
            new QuoteNode(
                Token::T_QUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a'))
            ),
            $this->parse('\'a')
        );
    }

    public function test_unquote(): void
    {
        self::assertEquals(
            new QuoteNode(
                Token::T_UNQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a'))
            ),
            $this->parse(',a')
        );
    }

    public function test_unquote_splice(): void
    {
        self::assertEquals(
            new QuoteNode(
                Token::T_UNQUOTE_SPLICING,
                $this->loc(1, 0),
                $this->loc(1, 3),
                new SymbolNode('a', $this->loc(1, 2), $this->loc(1, 3), Symbol::create('a'))
            ),
            $this->parse(',@a')
        );
    }

    public function test_quasiquote1(): void
    {
        self::assertEquals(
            new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 8),
                new SymbolNode('unquote', $this->loc(1, 1), $this->loc(1, 8), Symbol::create('unquote'))
            ),
            $this->parse(sprintf('`%s', Symbol::NAME_UNQUOTE))
        );
    }

    public function test_quasiquote2(): void
    {
        self::assertEquals(
            new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a'))
            ),
            $this->parse('`a')
        );
    }

    public function test_read_string(): void
    {
        self::assertEquals(
            new StringNode('"abc"', $this->loc(1, 0), $this->loc(1, 5), 'abc'),
            $this->parse('"abc"')
        );

        self::assertEquals(
            new StringNode('"ab\"c"', $this->loc(1, 0), $this->loc(1, 7), 'ab"c'),
            $this->parse('"ab\"c"')
        );

        self::assertEquals(
            new StringNode('"\\\\\r\n\t\f\v\e\$"', $this->loc(1, 0), $this->loc(1, 18), "\\\r\n\t\f\v\e\$"),
            $this->parse('"\\\\\r\n\t\f\v\e\$"')
        );

        self::assertEquals(
            new StringNode('"read $abc sign"', $this->loc(1, 0), $this->loc(1, 16), 'read $abc sign'),
            $this->parse('"read $abc sign"'),
        );

        self::assertEquals(
            new StringNode('"\x41"', $this->loc(1, 0), $this->loc(1, 6), "\x41"),
            $this->parse('"\x41"')
        );

        self::assertEquals(
            new StringNode('"\u{65}"', $this->loc(1, 0), $this->loc(1, 8), "\u{65}"),
            $this->parse('"\u{65}"')
        );

        self::assertEquals(
            new StringNode('"\u{129}"', $this->loc(1, 0), $this->loc(1, 9), "\u{129}"),
            $this->parse('"\u{129}"')
        );

        self::assertEquals(
            new StringNode('"\u{1000}"', $this->loc(1, 0), $this->loc(1, 10), "\u{1000}"),
            $this->parse('"\u{1000}"')
        );

        self::assertEquals(
            new StringNode('"\u{10000}"', $this->loc(1, 0), $this->loc(1, 11), "\u{10000}"),
            $this->parse('"\u{10000}"')
        );

        self::assertEquals(
            new StringNode('"\77"', $this->loc(1, 0), $this->loc(1, 5), "\77"),
            $this->parse('"\77"')
        );
    }

    public function test_read_empty_map(): void
    {
        self::assertEquals(
            new ListNode(Token::T_OPEN_BRACE, $this->loc(1, 0), $this->loc(1, 2), []),
            $this->parse('{}')
        );
    }

    public function test_read_map1(): void
    {
        self::assertEquals(
            new ListNode(Token::T_OPEN_BRACE, $this->loc(1, 0), $this->loc(1, 6), [
                new KeywordNode(':a', $this->loc(1, 1), $this->loc(1, 3), Keyword::create('a')),
                new WhitespaceNode(' ', $this->loc(1, 3), $this->loc(1, 4)),
                new NumberNode('1', $this->loc(1, 4), $this->loc(1, 5), 1),
            ]),
            $this->parse('{:a 1}')
        );
    }

    public function test_read_map2(): void
    {
        self::assertEquals(
            new ListNode(Token::T_OPEN_BRACE, $this->loc(1, 0), $this->loc(1, 11), [
                new KeywordNode(':a', $this->loc(1, 1), $this->loc(1, 3), Keyword::create('a')),
                new WhitespaceNode(' ', $this->loc(1, 3), $this->loc(1, 4)),
                new NumberNode('1', $this->loc(1, 4), $this->loc(1, 5), 1),
                new WhitespaceNode(' ', $this->loc(1, 5), $this->loc(1, 6)),
                new KeywordNode(':b', $this->loc(1, 6), $this->loc(1, 8), Keyword::create('b')),
                new WhitespaceNode(' ', $this->loc(1, 8), $this->loc(1, 9)),
                new NumberNode('2', $this->loc(1, 9), $this->loc(1, 10), 2),
            ]),
            $this->parse('{:a 1 :b 2}')
        );
    }

    public function test_meta_keyword(): void
    {
        self::assertEquals(
            new MetaNode(
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), Keyword::create('test')),
                $this->loc(1, 0),
                $this->loc(1, 11),
                [
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
                ]
            ),
            $this->parse('^:test test')
        );
    }

    public function test_meta_string(): void
    {
        self::assertEquals(
            new MetaNode(
                new StringNode('"test"', $this->loc(1, 1), $this->loc(1, 7), 'test'),
                $this->loc(1, 0),
                $this->loc(1, 12),
                [
                    new WhitespaceNode(' ', $this->loc(1, 7), $this->loc(1, 8)),
                    new SymbolNode('test', $this->loc(1, 8), $this->loc(1, 12), Symbol::create('test')),
                ]
            ),
            $this->parse('^"test" test')
        );
    }

    public function test_meta_symbol(): void
    {
        self::assertEquals(
            new MetaNode(
                new SymbolNode('String', $this->loc(1, 1), $this->loc(1, 7), Symbol::create('String')),
                $this->loc(1, 0),
                $this->loc(1, 12),
                [
                    new WhitespaceNode(' ', $this->loc(1, 7), $this->loc(1, 8)),
                    new SymbolNode('test', $this->loc(1, 8), $this->loc(1, 12), Symbol::create('test')),
                ]
            ),
            $this->parse('^String test')
        );
    }

    public function test_meta_map(): void
    {
        self::assertEquals(
            new MetaNode(
                new ListNode(Token::T_OPEN_BRACE, $this->loc(1, 1), $this->loc(1, 12), [
                    new KeywordNode(':a', $this->loc(1, 2), $this->loc(1, 4), Keyword::create('a')),
                    new WhitespaceNode(' ', $this->loc(1, 4), $this->loc(1, 5)),
                    new NumberNode('1', $this->loc(1, 5), $this->loc(1, 6), 1),
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new KeywordNode(':b', $this->loc(1, 7), $this->loc(1, 9), Keyword::create('b')),
                    new WhitespaceNode(' ', $this->loc(1, 9), $this->loc(1, 10)),
                    new NumberNode('2', $this->loc(1, 10), $this->loc(1, 11), 2),
                ]),
                $this->loc(1, 0),
                $this->loc(1, 17),
                [
                    new WhitespaceNode(' ', $this->loc(1, 12), $this->loc(1, 13)),
                    new SymbolNode('test', $this->loc(1, 13), $this->loc(1, 17), Symbol::create('test')),
                ]
            ),
            $this->parse('^{:a 1 :b 2} test')
        );
    }

    public function test_concat_meta(): void
    {
        self::assertEquals(
            new MetaNode(
                new KeywordNode(':a', $this->loc(1, 1), $this->loc(1, 3), Keyword::create('a')),
                $this->loc(1, 0),
                $this->loc(1, 12),
                [
                    new WhitespaceNode(' ', $this->loc(1, 3), $this->loc(1, 4)),
                    new MetaNode(
                        new KeywordNode(':b', $this->loc(1, 5), $this->loc(1, 7), Keyword::create('b')),
                        $this->loc(1, 4),
                        $this->loc(1, 12),
                        [
                            new WhitespaceNode(' ', $this->loc(1, 7), $this->loc(1, 8)),
                            new SymbolNode('test', $this->loc(1, 8), $this->loc(1, 12), Symbol::create('test')),
                        ]
                    ),
                ]
            ),
            $this->parse('^:a ^:b test')
        );
    }

    public function test_read_short_fn_zero_args(): void
    {
        self::assertEquals(
            new ListNode(Token::T_FN, $this->loc(1, 0), $this->loc(1, 6), [
                new SymbolNode('add', $this->loc(1, 2), $this->loc(1, 5), Symbol::create('add')),
            ]),
            $this->parse('|(add)')
        );
    }

    public function test_read_short_fn_one_arg(): void
    {
        self::assertEquals(
            new ListNode(Token::T_FN, $this->loc(1, 0), $this->loc(1, 8), [
                new SymbolNode('add', $this->loc(1, 2), $this->loc(1, 5), Symbol::create('add')),
                new WhitespaceNode(' ', $this->loc(1, 5), $this->loc(1, 6)),
                new SymbolNode('$', $this->loc(1, 6), $this->loc(1, 7), Symbol::create('$')),
            ]),
            $this->parse('|(add $)')
        );
    }

    public function test_read_unbalanced_closed_paren(): void
    {
        $this->expectException(AbstractParserException::class);
        $this->expectExceptionMessage('Unterminated list');
        $this->parse(')');
    }

    public function test_read_unbalanced_open_paren(): void
    {
        $this->expectException(AbstractParserException::class);
        $this->expectExceptionMessage('Unterminated list');
        $this->parse('(');
    }

    public function test_read_unbalanced_open_brace(): void
    {
        $this->expectException(AbstractParserException::class);
        $this->expectExceptionMessage('Unterminated list');
        $this->parse('{');
    }

    public function test_eof(): void
    {
        $tokenStream = $this->compilerFacade->lexString('');

        self::assertNull($this->compilerFacade->parseNext($tokenStream));
    }

    public function test_invalid_generator(): void
    {
        $tokenStream = $this->compilerFacade->lexString('');

        $tokenStream->next();
        self::assertNull($this->compilerFacade->parseNext($tokenStream));
    }

    public function test_read_comment(): void
    {
        self::assertEquals(
            new CommentNode('# Test', $this->loc(1, 0), $this->loc(1, 6)),
            $this->parse('# Test')
        );
    }

    public function test_read_whitespace_only(): void
    {
        self::assertEquals(
            new WhitespaceNode(" \t", $this->loc(1, 0), $this->loc(1, 2)),
            $this->parse(" \t")
        );
    }

    public function test_read_newline(): void
    {
        self::assertEquals(
            new NewlineNode("\n", $this->loc(1, 0), $this->loc(2, 0)),
            $this->parse("\n")
        );
    }

    private function parse(string $string): NodeInterface
    {
        $tokenStream = $this->compilerFacade->lexString($string);

        return $this->compilerFacade->parseNext($tokenStream);
    }

    private function loc(int $line, int $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
