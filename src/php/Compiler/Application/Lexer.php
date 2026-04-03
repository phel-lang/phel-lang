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
use function sprintf;
use function strlen;

final class Lexer implements LexerInterface
{
    /**
     * IMPORTANT: The token type is determined by count($matches) after a successful regex match.
     * $matches[0] is always the full match, so count($matches) = capturing_group_index + 1.
     * The first REGEXP (group 1) yields count = 2 = T_WHITESPACE; each subsequent entry adds 1.
     * Adding, removing, or reordering entries shifts all subsequent indices and MUST be reflected
     * in the corresponding Token::T_* constants.
     */
    private const array REGEXPS = [
        "([ \t]+)", // Whitespace (index: 2)
        "(\r?\n)", // Newline (index: 3)
        '(#_)', // Inline comment (index: 4)
        "(#(?![_{\\|(\x22?])[^\n]*\n?|;[^\n]*\n?)", // Comment (# or ; excludes #_ #{ #( #" #?) (index: 5)
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
        '(#\()', // hash fn (index: 19)
        '("(?:[^"\\\\]++|\\\\.)*+")', // String (index: 20)
        "([^\(\)\[\]\{\}',`@ \n\r\t\#]+)", // Atom (index: 21)
        '(@)', // deref (index: 22)
        '(#"(?:[^"\\\\]++|\\\\.)*+")', // regex literal (index: 23)
        '(#\?\()', // reader conditional (index: 24 = T_READER_COND)
        '(#\?@\()', // reader conditional splicing (index: 25 = T_READER_COND_SPLICING)
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

                @trigger_error(
                    sprintf('Using "#| |#" for multiline comments is deprecated, use "(comment ...)" instead (at %s:%d:%d)', $source, $startLocation->getLine(), $startLocation->getColumn()),
                    E_USER_DEPRECATED,
                );

                yield new Token(Token::T_COMMENT, $comment, $startLocation, $endLocation);

                $startLocation = $endLocation;
                continue;
            }

            if (preg_match($this->combinedRegex, $code, $matches, 0, $this->cursor)) {
                $this->moveCursor($matches[0]);
                $endLocation = $this->createSourceLocation($source);
                $tokenType = count($matches);

                if ($tokenType === Token::T_COMMENT && str_starts_with($matches[0], '#')) {
                    @trigger_error(
                        sprintf('Using "#" for line comments is deprecated, use ";" instead (at %s:%d:%d)', $source, $startLocation->getLine(), $startLocation->getColumn()),
                        E_USER_DEPRECATED,
                    );
                }

                if ($tokenType === Token::T_FN) {
                    @trigger_error(
                        sprintf('Using "|()" for short functions is deprecated, use "#()" instead (at %s:%d:%d)', $source, $startLocation->getLine(), $startLocation->getColumn()),
                        E_USER_DEPRECATED,
                    );
                }

                yield new Token($tokenType, $matches[0], $startLocation, $endLocation);

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
