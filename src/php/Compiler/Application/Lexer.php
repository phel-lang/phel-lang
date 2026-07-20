<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Generator;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Lang\SourceLocation;
use Phel\Shared\Parser\Node\Token;

use function count;
use function mb_strlen;
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
        "(#(?![_{\\|(\x22?#a-zA-Z'])[^\n]*\n?|;[^\n]*\n?)", // Comment (# or ; excludes #_ #{ #( #" #? ## #<letter> #') (index: 5)
        '(#\{)', // open hash brace (index: 6)
        '(,@|~@)', // unquote-splicing (index: 7), accepts `,@` or Clojure-style `~@`
        "(\()", // open parenthesis (index: 8)
        "(\))", // close parenthesis (index: 9)
        "(\[)", // open bracket (index: 10)
        "(\])", // close bracket (index: 11)
        "(\{)", // open brace (index: 12)
        "(\})", // close brace (index: 13)
        "(')", // quote (index: 14)
        '(,|~)', // unquote (index: 15), accepts `,` or Clojure-style `~`
        '(`)', // quasiquote (index: 16)
        "(\^)", // caret (index: 17)
        "(\|\()", // short fn (index: 18)
        '(#\()', // hash fn (index: 19)
        '("(?:[^"\\\\]++|\\\\.)*+")', // String (index: 20)
        '(\\\\(?:space|newline|tab|formfeed|backspace|return|u[0-9a-fA-F]{4}|o[0-7]{1,3}|[^\s])(?![A-Za-z0-9_\-\\\\]))', // Character literal (index: 21 = T_CHAR) - Clojure-style \a, \A, \1, \space, \newline, \uNNNN, \oNNN, \(, \), etc. Must precede the atom rule so it wins on unambiguous cases; falls through to atom when followed by identifier continuation or another backslash (preserving FQN parsing for \Phel\Lang\Symbol).
        "([^\(\)\[\]\{\},`@ \n\r\t\#]+\#?)", // Atom (index: 22), trailing # allowed for gensym syntax (e.g. foo#); leading ' is claimed by the quote rule above so only mid/trailing ' reaches here (e.g. a', foo'')
        '(@)', // deref (index: 23)
        '(#"(?:[^"\\\\]++|\\\\.)*+")', // regex literal (index: 24)
        '(#\?\()', // reader conditional (index: 25 = T_READER_COND)
        '(#\?@\()', // reader conditional splicing (index: 26 = T_READER_COND_SPLICING)
        '(##(?:-?Inf|NaN)(?![A-Za-z0-9_\-]))', // symbolic number literal (index: 27 = T_SYMBOLIC_NUMBER) - Clojure-style ##Inf, ##-Inf, ##NaN
        '(#[A-Za-z][A-Za-z0-9_\-]*(?:\.[A-Za-z0-9_\-]+)*(?:\/[A-Za-z][A-Za-z0-9_\-]*(?:\.[A-Za-z0-9_\-]+)*)?)', // tagged literal start (index: 28 = T_TAGGED_LITERAL) - e.g. #cpp, #uuid, #inst, #my.app/Person (EDN-style namespaced tags)
        "(#')", // var-quote prefix (index: 29 = T_VAR_QUOTE) - `#'foo` expands to `(var foo)` in the reader, yielding a `PhelVar` handle to the named definition
    ];

    /**
     * Token types that can trigger a deprecation warning. Common tokens
     * (parens, atoms, whitespace, ...) are absent, so a single isset() lookup
     * lets them skip the per-token deprecation checks below entirely.
     *
     * @var array<int,true>
     */
    private const array DEPRECATABLE_TYPES = [
        Token::T_COMMENT => true,
        Token::T_FN => true,
        Token::T_UNQUOTE_SPLICING => true,
        Token::T_UNQUOTE => true,
    ];

    private const string MULTILINE_COMMENT_BEGIN = '#|';

    private const string MULTILINE_COMMENT_END = '|#';

    private int $cursor = 0;

    private int $line = 1;

    private int $column = 0;

    /**
     * Whole-source ASCII flag, computed once per lexString. When the source
     * contains no high-bit bytes, byte length equals code-point length, so
     * column math can use the cheaper strlen() instead of mb_strlen().
     */
    private bool $isAscii = true;

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
     * @return Generator<int, Token>
     */
    private function lexStringGenerator(string $code, string $source, int $startingLine): Generator
    {
        $this->cursor = 0;
        $this->line = $startingLine;
        $this->column = 0;
        // One-time whole-source scan: most Phel source is pure ASCII, where
        // strlen() === mb_strlen(), so moveCursor can take the cheaper path.
        $this->isAscii = preg_match('/[\x80-\xFF]/', $code) === 0;
        $end = strlen($code);

        $startLocation = $this->createSourceLocation($source);

        while ($this->cursor < $end) {
            if (substr($code, $this->cursor, 2) === self::MULTILINE_COMMENT_BEGIN) {
                $comment = $this->readMultilineComment($code, $source);
                $this->moveCursor($comment);
                $endLocation = $this->createSourceLocation($source);

                @trigger_error(
                    sprintf('"#| ... |#" multiline comments are deprecated and will be removed in Phel v0.33. Use ";;" for line comments or "#_" to skip a single form (at %s:%d:%d)', $source, $startLocation->getLine(), $startLocation->getColumn()),
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

                if (isset(self::DEPRECATABLE_TYPES[$tokenType])) {
                    if ($tokenType === Token::T_COMMENT && str_starts_with($matches[0], '#')) {
                        @trigger_error(
                            sprintf('Bare "#" line comments are deprecated and will be removed in Phel v0.33. Use ";" or ";;" instead (at %s:%d:%d)', $source, $startLocation->getLine(), $startLocation->getColumn()),
                            E_USER_DEPRECATED,
                        );
                    }

                    if ($tokenType === Token::T_FN) {
                        @trigger_error(
                            sprintf('Using "|()" for short functions is deprecated, use "#()" instead (at %s:%d:%d)', $source, $startLocation->getLine(), $startLocation->getColumn()),
                            E_USER_DEPRECATED,
                        );
                    }

                    if ($tokenType === Token::T_UNQUOTE_SPLICING && $matches[0] === ',@') {
                        @trigger_error(
                            sprintf('Using "," for unquote-splicing is deprecated, use "~@" instead (at %s:%d:%d)', $source, $startLocation->getLine(), $startLocation->getColumn()),
                            E_USER_DEPRECATED,
                        );
                    }

                    if ($tokenType === Token::T_UNQUOTE && $matches[0] === ',') {
                        @trigger_error(
                            sprintf('Using "," for unquote is deprecated, use "~" instead (at %s:%d:%d)', $source, $startLocation->getLine(), $startLocation->getColumn()),
                            E_USER_DEPRECATED,
                        );
                    }
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
        // The cursor indexes into the raw byte string (it feeds preg_match's
        // offset and substr), so it must advance by the byte length. Columns,
        // however, are reported in code points so error locations line up with
        // what a user sees in multibyte (UTF-8) source. For ASCII the two are
        // identical, so column numbers are unchanged for ASCII-only code.
        //
        // When the whole source is ASCII (see $this->isAscii, set once per
        // lexString) the byte count is the code-point count, so strlen() is
        // used for the column math in both branches as a fast path; multibyte
        // sources keep the mb_strlen() path for byte-for-byte identical
        // columns.
        $this->cursor += strlen($str);
        $lastNewLinePos = strrpos($str, "\n");

        if ($lastNewLinePos !== false) {
            $this->line += substr_count($str, "\n");
            $tail = substr($str, $lastNewLinePos + 1);
            $this->column = $this->isAscii ? strlen($tail) : mb_strlen($tail, 'UTF-8');
        } else {
            $this->column += $this->isAscii ? strlen($str) : mb_strlen($str, 'UTF-8');
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

        // Jump delimiter-to-delimiter instead of stepping one byte at a time:
        // advance to whichever of the next `#|`/`|#` comes first and adjust the
        // nesting depth. Equivalent to the per-byte scan but lets `strpos` skip
        // the comment body at C speed.
        while ($pos < $end) {
            $close = strpos($code, self::MULTILINE_COMMENT_END, $pos);
            if ($close === false) {
                // No terminator left: fall through to the unterminated throw.
                break;
            }

            $open = strpos($code, self::MULTILINE_COMMENT_BEGIN, $pos);
            if ($open !== false && $open < $close) {
                ++$depth;
                $pos = $open + 2;

                continue;
            }

            --$depth;
            $pos = $close + 2;

            if ($depth === 0) {
                return substr($code, $this->cursor, $pos - $this->cursor);
            }
        }

        throw LexerValueException::unexpectedLexerState($source, $this->line, $this->column);
    }
}
