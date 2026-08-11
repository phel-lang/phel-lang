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

        foreach ($this->compilerFacade->lexString($code, $source) as $token) {
            $type = $token->getType();

            if ($type === Token::T_EOF) {
                break;
            }

            if (!in_array($type, self::TRIVIA, true)) {
                $endsInLineComment = $type === Token::T_COMMENT;
            }

            if ($unterminatedStringLine === null && $this->isUnterminatedString($token)) {
                $unterminatedStringLine = $this->lineOf($token);
            }

            if (isset(self::CLOSER_TEXT_FOR_OPENER[$type])) {
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

        return new BalanceReport($stack, $unexpectedClosers, $unterminatedStringLine, $endsInLineComment);
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
