<?php

namespace Phel;

use Phel\Stream\SourceLocation;
use Phel\Stream\StringCharStream;
use Phel\Token\AtomToken;
use Phel\Token\CommentToken;
use Phel\Token\EOFToken;
use Phel\Token\StringToken;
use Phel\Token\SyntaxToken;
use Phel\Token\WhitespaceToken;
use \PHPUnit\Framework\TestCase;

class LexerTest extends TestCase {

    public function testWhitespace() {
        $this->assertEquals(
            [
                new WhitespaceToken(" \n\t\r\n", new SourceLocation("string", 1, 0), new SourceLocation("string", 3, 0)),
                new EOFToken(new SourceLocation("string", 3, 0))
            ],
            $this->lex(" \n\t\r\n")
        );
    }

    public function testReadCommentWithoutText() {
        $this->assertEquals(
            [
                new CommentToken("#", new SourceLocation("string", 1, 0), new SourceLocation("string", 1, 1)),
                new EOFToken(new SourceLocation("string", 1, 1))
            ],
            $this->lex("#")
        );
    }

    public function testReadCommentWithoutNewLine() {
        $this->assertEquals(
            [
                new CommentToken("# Mein Kommentar", new SourceLocation("string", 1, 0), new SourceLocation("string", 1, 16)),
                new EOFToken(new SourceLocation("string", 1, 16))
            ],
            $this->lex("# Mein Kommentar")
        );
    }

    public function testReadCommentWithNewLine() {
        $this->assertEquals(
            [
                new CommentToken("# Mein Kommentar", new SourceLocation("string", 1, 0), new SourceLocation("string", 1, 16)),
                new WhitespaceToken("\n", new SourceLocation("string", 1, 16), new SourceLocation("string", 2, 0)),
                new CommentToken("# Mein andere Kommentar", new SourceLocation("string", 2, 0), new SourceLocation("string", 2, 23)),
                new EOFToken(new SourceLocation("string", 2, 23))
            ],
            $this->lex("# Mein Kommentar\n# Mein andere Kommentar")
        );
    }

    public function testReadSingleSyntaxChar() {
        $this->assertEquals(
            [
                new SyntaxToken("(", new SourceLocation("string", 1, 0), new SourceLocation("string", 1, 1)),
                new EOFToken(new SourceLocation("string", 1, 1))
            ],
            $this->lex("(")
        );
    }

    public function testReadEmptyTuple() {
        $this->assertEquals(
            [
                new SyntaxToken("(", new SourceLocation("string", 1, 0), new SourceLocation("string", 1, 1)),
                new SyntaxToken(")", new SourceLocation("string", 1, 1), new SourceLocation("string", 1, 2)),
                new EOFToken(new SourceLocation("string", 1, 2))
            ],
            $this->lex("()")
        );
    }

    public function testReadWord() {
        $this->assertEquals(
            [
                new AtomToken("true", new SourceLocation("string", 1, 0), new SourceLocation("string", 1, 4)),
                new EOFToken(new SourceLocation("string", 1, 4))
            ],
            $this->lex("true")
        );
    }

    public function testReadNumber() {
        $this->assertEquals(
            [
                new AtomToken("1", new SourceLocation("string", 1, 0), new SourceLocation("string", 1, 1)),
                new EOFToken(new SourceLocation("string", 1, 1))
            ],
            $this->lex("1")
        );
    }

    public function testReadEmptyString() {
        $this->assertEquals(
            [
                new StringToken('""', new SourceLocation("string", 1, 0), new SourceLocation("string", 1, 2)),
                new EOFToken(new SourceLocation("string", 1, 2))
            ],
            $this->lex('""')
        );
    }

    public function testReadString() {
        $this->assertEquals(
            [
                new StringToken('"test"', new SourceLocation("string", 1, 0), new SourceLocation("string", 1, 6)),
                new EOFToken(new SourceLocation("string", 1, 6))
            ],
            $this->lex('"test"')
        );
    }

    public function testReadEscapedString() {
        $this->assertEquals(
            [
                new StringToken('"te\\"st"', new SourceLocation("string", 1, 0), new SourceLocation("string", 1, 8)),
                new EOFToken(new SourceLocation("string", 1, 8))
            ],
            $this->lex('"te\\"st"')
        );
    }

    public function testReadTuple() {
        $this->assertEquals(
            [
                new SyntaxToken("[", new SourceLocation("string", 1, 0), new SourceLocation("string", 1, 1)),
                new AtomToken("true", new SourceLocation("string", 1, 1), new SourceLocation("string", 1, 5)),
                new WhitespaceToken(" ", new SourceLocation("string", 1, 5), new SourceLocation("string", 1, 6)),
                new AtomToken("false", new SourceLocation("string", 1, 6), new SourceLocation("string", 1, 11)),
                new SyntaxToken("]", new SourceLocation("string", 1, 11), new SourceLocation("string", 1, 12)),
                new EOFToken(new SourceLocation("string", 1, 12))
            ],
            $this->lex("[true false]")
        );
    }

    private function lex($string) {
        $lexer = new Lexer();
        return iterator_to_array($lexer->lexString($string, 'string'));
    }
}