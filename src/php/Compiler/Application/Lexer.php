<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Generator;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Lang\SourceLocation;

use function count;
use function strlen;

final class Lexer implements LexerInterface
{
    private const array REGEXPS = [
        "([ \t]+)", // Whitespace (index: 2)
        "(\r?\n)", // Newline (index: 3)
        '(#_)', // Inline comment (index: 4)
        "(#(?![_{\\|])[^\n]*\n?|;[^\n]*\n?)", // Comment (# or ;) (index: 5)
        '(#\{)', // open hash brace (index: 6)
        '(,@)', // unquote-splicing (index: 7)
        "(\()", // open parenthesis (index: 8)
        "(\))", // close parenthesis (index: 9)
        "(\[)", // open bracket (index: 10)
        "(\])", // close bracket (index: 11)
        "(\{)", // open brace (index: 12)
        "(\})", // close brace (index: 13)
        "(')", // quote (index: 14)
        '(,)', // unquote (index: 15)
        '(`)', // quasiquote (index: 16)
        "(\^)", // caret (index: 17)
        "(\|\()", // short fn (index: 18)
        '("(?:[^"\\\\]++|\\\\.)*+")', // String (index: 19)
        "([^\(\)\[\]\{\}',`@ \n\r\t\#]+)", // Atom (index: 20)
    ];

    private const string MULTILINE_COMMENT_BEGIN = '#|';

    private const string MULTILINE_COMMENT_END = '|#';

    private int $cursor = 0;

    private int $line = 1;

    private int $column = 0;

    private readonly string $combinedRegex;

    public function __construct(
        private readonly bool $withLocation = true,
    ) {
        $this->combinedRegex = '/(?:' . implode('|', self::REGEXPS) . ')/mA';
    }

    /**
     * @throws LexerValueException
     */
    public function lexString(string $code, string $source = self::DEFAULT_SOURCE, int $startingLine = 1): TokenStream
    {
        return new TokenStream($this->lexStringGenerator($code, $source, $startingLine));
    }

    /**
     * @throws LexerValueException
     *
     * @return Generator<Token>
     */
    private function lexStringGenerator(string $code, string $source, int $startingLine): Generator
    {
        $this->cursor = 0;
        $this->line = $startingLine;
        $this->column = 0;
        $end = strlen($code);

        $startLocation = $this->createSourceLocation($source);

        while ($this->cursor < $end) {
            if (substr($code, $this->cursor, 2) === self::MULTILINE_COMMENT_BEGIN) {
                $comment = $this->readMultilineComment($code, $source);
                $this->moveCursor($comment);
                $endLocation = $this->createSourceLocation($source);

                yield new Token(Token::T_COMMENT, $comment, $startLocation, $endLocation);

                $startLocation = $endLocation;
                continue;
            }

            if (preg_match($this->combinedRegex, $code, $matches, 0, $this->cursor)) {
                $this->moveCursor($matches[0]);
                $endLocation = $this->createSourceLocation($source);

                yield new Token(count($matches), $matches[0], $startLocation, $endLocation);

                $startLocation = $endLocation;
            } else {
                throw LexerValueException::unexpectedLexerState($source, $this->line, $this->column);
            }
        }

        yield new Token(Token::T_EOF, '', $startLocation, $startLocation);
    }

    private function moveCursor(string $str): void
    {
        $len = strlen($str);
        $this->cursor += $len;
        $lastNewLinePos = strrpos($str, "\n");

        if ($lastNewLinePos !== false) {
            $this->line += substr_count($str, "\n");
            $this->column = $len - $lastNewLinePos - 1;
        } else {
            $this->column += $len;
        }
    }

    private function createSourceLocation(string $source): SourceLocation
    {
        if ($this->withLocation) {
            return new SourceLocation($source, $this->line, $this->column);
        }

        return new SourceLocation('string', 0, 0);
    }

    /**
     * @throws LexerValueException
     */
    private function readMultilineComment(string $code, string $source): string
    {
        $pos = $this->cursor;
        $depth = 0;
        $end = strlen($code);

        while ($pos < $end) {
            if (substr($code, $pos, 2) === self::MULTILINE_COMMENT_BEGIN) {
                ++$depth;
                $pos += 2;

                continue;
            }

            if (substr($code, $pos, 2) === self::MULTILINE_COMMENT_END) {
                --$depth;
                $pos += 2;

                if ($depth === 0) {
                    return substr($code, $this->cursor, $pos - $this->cursor);
                }

                continue;
            }

            ++$pos;
        }

        throw LexerValueException::unexpectedLexerState($source, $this->line, $this->column);
    }
}
