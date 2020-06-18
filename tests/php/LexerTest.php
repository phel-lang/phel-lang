<?php

declare(strict_types=1);

namespace Phel;

use Phel\Lang\SourceLocation;
use PHPUnit\Framework\TestCase;

final class LexerTest extends TestCase
{
    public function testReadCommentWithoutText(): void
    {
        $this->assertEquals(
            [
                new Token(Token::T_COMMENT, '#', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 1)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 1), new SourceLocation('string', 1, 1))
            ],
            $this->lex('#')
        );
    }

    public function testReadCommentWithoutNewLine(): void
    {
        $this->assertEquals(
            [
                new Token(Token::T_COMMENT, '# Mein Kommentar', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 16)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 16), new SourceLocation('string', 1, 16))
            ],
            $this->lex('# Mein Kommentar')
        );
    }

    public function testReadCommentWithNewLine(): void
    {
        $this->assertEquals(
            [
                new Token(Token::T_COMMENT, '# Mein Kommentar', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 16)),
                new Token(Token::T_WHITESPACE, "\n", new SourceLocation('string', 1, 16), new SourceLocation('string', 2, 0)),
                new Token(Token::T_COMMENT, '# Mein andere Kommentar', new SourceLocation('string', 2, 0), new SourceLocation('string', 2, 23)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 2, 23), new SourceLocation('string', 2, 23))
            ],
            $this->lex("# Mein Kommentar\n# Mein andere Kommentar")
        );
    }

    public function testReadSingleSyntaxChar(): void
    {
        $this->assertEquals(
            [
                new Token(Token::T_OPEN_PARENTHESIS, '(', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 1)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 1), new SourceLocation('string', 1, 1))
            ],
            $this->lex('(')
        );
    }

    public function testReadEmptyTuple(): void
    {
        $this->assertEquals(
            [
                new Token(Token::T_OPEN_PARENTHESIS, '(', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 1)),
                new Token(Token::T_CLOSE_PARENTHESIS, ')', new SourceLocation('string', 1, 1), new SourceLocation('string', 1, 2)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 2), new SourceLocation('string', 1, 2))
            ],
            $this->lex('()')
        );
    }

    public function testReadWord(): void
    {
        $this->assertEquals(
            [
                new Token(Token::T_ATOM, 'true', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 4)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 4), new SourceLocation('string', 1, 4))
            ],
            $this->lex('true')
        );
    }

    public function testReadNumber(): void
    {
        $this->assertEquals(
            [
                new Token(Token::T_ATOM, '1', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 1)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 1), new SourceLocation('string', 1, 1))
            ],
            $this->lex('1')
        );
    }

    public function testReadEmptyString(): void
    {
        $this->assertEquals(
            [
                new Token(Token::T_STRING, '""', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 2)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 2), new SourceLocation('string', 1, 2))
            ],
            $this->lex('""')
        );
    }

    public function testReadString(): void
    {
        $this->assertEquals(
            [
                new Token(Token::T_STRING, '"test"', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 6)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 6), new SourceLocation('string', 1, 6))
            ],
            $this->lex('"test"')
        );
    }

    public function testReadEscapedString(): void
    {
        $this->assertEquals(
            [
                new Token(Token::T_STRING, '"te\\"st"', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 8)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 8), new SourceLocation('string', 1, 8))
            ],
            $this->lex('"te\\"st"')
        );
    }

    public function testReadTuple(): void
    {
        $this->assertEquals(
            [
                new Token(Token::T_OPEN_BRACKET, '[', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 1)),
                new Token(Token::T_ATOM, 'true', new SourceLocation('string', 1, 1), new SourceLocation('string', 1, 5)),
                new Token(Token::T_WHITESPACE, ' ', new SourceLocation('string', 1, 5), new SourceLocation('string', 1, 6)),
                new Token(Token::T_ATOM, 'false', new SourceLocation('string', 1, 6), new SourceLocation('string', 1, 11)),
                new Token(Token::T_CLOSE_BRACKET, ']', new SourceLocation('string', 1, 11), new SourceLocation('string', 1, 12)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 12), new SourceLocation('string', 1, 12))
            ],
            $this->lex('[true false]')
        );
    }

    private function lex(string $string): array
    {
        $lexer = new Lexer();
        return iterator_to_array($lexer->lexString($string, 'string'));
    }
}
