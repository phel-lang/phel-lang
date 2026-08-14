<?php

declare(strict_types=1);

namespace Phel\Balance\Application;

use Phel\Balance\Domain\BalanceReport;
use Phel\Balance\Domain\OpenDelimiter;
use Phel\Balance\Domain\UnexpectedCloser;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Parser\Node\Token;

use function array_pop;
use function in_array;
use function str_starts_with;

/**
 * Tracks nesting on the lexer's token stream.
 *
 * The token stream, not the bytes: `\(` and `\)` are character literals, and a
 * `(` inside a string, a `;` comment or a `#"regex"` belongs to that one token.
 * A byte counter gets every one of those wrong. The lexer never parses, so it
 * happily tokenizes a file whose delimiters do not match.
 *
 * @internal
 */
final readonly class DelimiterScanner
{
    /**
     * Each token that opens a nesting level, mapped to the text that closes it.
     * `#(`, `#?(` and `#?@(` swallow their `(` into one token and there are no
     * dedicated closing types for them, so this is a lookup rather than a
     * character flip.
     */
    private const array CLOSER_TEXT_FOR_OPENER = [
        Token::T_OPEN_PARENTHESIS => ')',
        Token::T_HASH_FN => ')',
        Token::T_READER_COND => ')',
        Token::T_READER_COND_SPLICING => ')',
        Token::T_OPEN_BRACKET => ']',
        Token::T_OPEN_BRACE => '}',
        Token::T_HASH_OPEN_BRACE => '}',
    ];

    private const array TEXT_FOR_CLOSER = [
        Token::T_CLOSE_PARENTHESIS => ')',
        Token::T_CLOSE_BRACKET => ']',
        Token::T_CLOSE_BRACE => '}',
    ];

    private const array TRIVIA = [
        Token::T_WHITESPACE,
        Token::T_NEWLINE,
    ];

    /**
     * Reader prefixes that consume the form after them. A closer appended
     * directly behind one becomes that form, so the file stops parsing even
     * though the delimiters now count out.
     */
    private const array PREFIX_AWAITING_A_FORM = [
        Token::T_QUOTE,
        Token::T_QUASIQUOTE,
        Token::T_UNQUOTE,
        Token::T_UNQUOTE_SPLICING,
        Token::T_CARET,
        Token::T_DEREF,
        Token::T_VAR_QUOTE,
        Token::T_COMMENT_MACRO,
        Token::T_TAGGED_LITERAL,
    ];

    /**
     * Heads that only ever open a definition. One of them on a line after the
     * outermost unclosed level is the same signal a column-0 opener is, and
     * survives the indentation a half-written file usually carries.
     */
    private const array DEFINITION_HEADS = [
        'ns',
        'def',
        'def-',
        'defn',
        'defn-',
        'defmacro',
        'defmacro-',
        'defstruct',
        'definterface',
        'defexception',
        'defprotocol',
        'defmulti',
        'defmethod',
        'defonce',
        'declare',
        'deftest',
        'defbench',
    ];

    /** An incomplete character literal: the `\` has not read its character yet. */
    private const string DANGLING_CHAR_LITERAL = '\\';

    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    public function scan(string $code, string $source): BalanceReport
    {
        /** @var list<OpenDelimiter> $stack */
        $stack = [];
        /** @var list<UnexpectedCloser> $unexpectedClosers */
        $unexpectedClosers = [];
        $unterminatedStringLine = null;
        $endsInLineComment = false;
        $danglingPrefixToken = null;
        $pendingPrefixColumn = null;
        $openerAwaitingHead = null;
        /** @var list<int> $topLevelOpenerLines */
        $topLevelOpenerLines = [];

        foreach ($this->compilerFacade->lexString($code, $source) as $token) {
            $type = $token->getType();

            if ($type === Token::T_EOF) {
                break;
            }

            if (!in_array($type, self::TRIVIA, true)) {
                $endsInLineComment = $type === Token::T_COMMENT;
                $danglingPrefixToken = $this->isPrefixAwaitingAForm($token)
                    ? $token->getCode()
                    : null;

                // A prefix run keeps the column of the token that began it, so
                // `'(defn ...)` at the margin still reads as a top-level form.
                if (in_array($type, self::PREFIX_AWAITING_A_FORM, true)) {
                    $pendingPrefixColumn ??= $this->columnOf($token);
                } elseif (!isset(self::CLOSER_TEXT_FOR_OPENER[$type])) {
                    $pendingPrefixColumn = null;
                }

                if ($openerAwaitingHead !== null) {
                    if ($type === Token::T_ATOM && in_array($token->getCode(), self::DEFINITION_HEADS, true)) {
                        $topLevelOpenerLines[] = $openerAwaitingHead;
                    }

                    $openerAwaitingHead = null;
                }
            }

            if ($unterminatedStringLine === null && $this->isUnterminatedString($token)) {
                $unterminatedStringLine = $this->lineOf($token);
            }

            if (isset(self::CLOSER_TEXT_FOR_OPENER[$type])) {
                if (($pendingPrefixColumn ?? $this->columnOf($token)) === 0) {
                    $topLevelOpenerLines[] = $this->lineOf($token);
                } else {
                    $openerAwaitingHead = $this->lineOf($token);
                }

                $pendingPrefixColumn = null;

                $stack[] = new OpenDelimiter(
                    $token->getCode(),
                    self::CLOSER_TEXT_FOR_OPENER[$type],
                    $this->lineOf($token),
                    $this->columnOf($token),
                );

                continue;
            }

            if (!isset(self::TEXT_FOR_CLOSER[$type])) {
                continue;
            }

            $closerText = self::TEXT_FOR_CLOSER[$type];
            $open = array_pop($stack);

            if (!$open instanceof OpenDelimiter) {
                $unexpectedClosers[] = new UnexpectedCloser($closerText, null, $this->lineOf($token), $this->columnOf($token));

                continue;
            }

            if ($open->closerText !== $closerText) {
                // A wrong closer is not a missing one: `(foo]` could have meant
                // `(foo)` or `[foo]`, and picking one rewrites intent. Push the
                // level back so the levels outside it still reconcile.
                $stack[] = $open;
                $unexpectedClosers[] = new UnexpectedCloser($closerText, $open, $this->lineOf($token), $this->columnOf($token));
            }
        }

        return new BalanceReport(
            $stack,
            $unexpectedClosers,
            $unterminatedStringLine,
            $this->topLevelFormLineAfter($stack, $topLevelOpenerLines),
            $danglingPrefixToken,
            $endsInLineComment,
        );
    }

    /**
     * A form opening at column 0 after the outermost unclosed level means the
     * author closed nothing and moved on to a new top-level form, so the missing
     * closer belongs back there and not at the end of the file. Appending at the
     * end instead produces a file that compiles with everything after the defect
     * nested inside the open form, which is a silent change of meaning.
     *
     * Column 0 is the top-level convention `phel format` already enforces, and
     * it is read off token positions rather than guessed. A reader prefix keeps
     * the column of the token that began it, so `'(defn ...)` at the margin
     * counts, and a definition head counts at any column, because a file with a
     * missing closer is usually mid-edit and no longer formatted. All three are
     * used only to refuse: a wrong reading costs a manual fix, never a rewritten
     * program.
     *
     * @param list<OpenDelimiter> $stack
     * @param list<int>           $topLevelOpenerLines
     */
    private function topLevelFormLineAfter(array $stack, array $topLevelOpenerLines): ?int
    {
        if ($stack === []) {
            return null;
        }

        foreach ($topLevelOpenerLines as $line) {
            if ($line > $stack[0]->line) {
                return $line;
            }
        }

        return null;
    }

    /**
     * A lone `\` is a character literal still waiting for its character, so an
     * appended closer becomes that character: `\)` counts out, closes nothing,
     * and leaves the file exactly as unbalanced as it started while the run
     * reports a repair. With no character after it the char rule does not match
     * and it falls through to the atom rule, the same way an unclosed `"` does.
     */
    private function isPrefixAwaitingAForm(Token $token): bool
    {
        if (in_array($token->getType(), self::PREFIX_AWAITING_A_FORM, true)) {
            return true;
        }

        return $token->getCode() === self::DANGLING_CHAR_LITERAL
            && in_array($token->getType(), [Token::T_ATOM, Token::T_CHAR], true);
    }

    /**
     * The atom rule excludes only bracket and hash bytes, so an unclosed `"`
     * falls through to it instead of raising. No valid Phel atom starts with a
     * quote, which makes the leading `"` an exact signal.
     */
    private function isUnterminatedString(Token $token): bool
    {
        return $token->getType() === Token::T_ATOM
            && str_starts_with($token->getCode(), '"');
    }

    private function lineOf(Token $token): int
    {
        return $token->getStartLocation()->getLine();
    }

    private function columnOf(Token $token): int
    {
        return $token->getStartLocation()->getColumn();
    }
}
