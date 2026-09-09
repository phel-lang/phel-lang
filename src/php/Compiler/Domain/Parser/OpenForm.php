<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser;

use Phel\Lang\SourceLocation;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\Node\Token;

use function sprintf;

/**
 * A form whose opening delimiter has been read and whose closer has not.
 *
 * A missing closer is only diagnosable from the opening side. The place the
 * parser notices it (end of file, or the wrong closer) carries neither the line
 * the author has to fix nor the bracket they still owe.
 *
 * @internal
 */
final readonly class OpenForm
{
    /**
     * `#(` and `#{` swallow their delimiter into a single token and have no
     * closing type of their own, so opener to closer is a lookup rather than a
     * character flip.
     */
    private const array CLOSER_TEXT_FOR_OPENER = [
        Token::T_OPEN_PARENTHESIS => ')',
        Token::T_HASH_FN => ')',
        Token::T_OPEN_BRACKET => ']',
        Token::T_OPEN_BRACE => '}',
        Token::T_HASH_OPEN_BRACE => '}',
    ];

    private const array FORM_NAME_FOR_OPENER = [
        Token::T_OPEN_PARENTHESIS => 'list',
        Token::T_HASH_FN => 'list',
        Token::T_OPEN_BRACKET => 'vector',
        Token::T_OPEN_BRACE => 'map',
        Token::T_HASH_OPEN_BRACE => 'set',
    ];

    /** PHEL103 is spelled `UNTERMINATED_TABLE` from when `#{}` sets were still written as tables. */
    private const array ERROR_CODE_FOR_OPENER = [
        Token::T_OPEN_PARENTHESIS => ErrorCode::UNTERMINATED_LIST,
        Token::T_HASH_FN => ErrorCode::UNTERMINATED_LIST,
        Token::T_OPEN_BRACKET => ErrorCode::UNTERMINATED_VECTOR,
        Token::T_OPEN_BRACE => ErrorCode::UNTERMINATED_MAP,
        Token::T_HASH_OPEN_BRACE => ErrorCode::UNTERMINATED_TABLE,
    ];

    public function __construct(
        private Token $openToken,
    ) {}

    public function getOpenToken(): Token
    {
        return $this->openToken;
    }

    public function getStartLocation(): SourceLocation
    {
        return $this->openToken->getStartLocation();
    }

    public function getErrorCode(): ErrorCode
    {
        return self::ERROR_CODE_FOR_OPENER[$this->openToken->getType()] ?? ErrorCode::UNTERMINATED_LIST;
    }

    public function getCloserText(): string
    {
        return self::CLOSER_TEXT_FOR_OPENER[$this->openToken->getType()] ?? ')';
    }

    public function unterminatedMessage(): string
    {
        return sprintf(
            "Unterminated %s starting at line %d. Did you forget a closing '%s'?",
            $this->formName(),
            $this->getStartLocation()->getLine(),
            $this->getCloserText(),
        );
    }

    public function mismatchedCloserMessage(string $foundCloserText): string
    {
        return sprintf(
            "Expected '%s' to close the %s opened at line %d, found '%s'.",
            $this->getCloserText(),
            $this->formName(),
            $this->getStartLocation()->getLine(),
            $foundCloserText,
        );
    }

    private function formName(): string
    {
        return self::FORM_NAME_FOR_OPENER[$this->openToken->getType()] ?? 'list';
    }
}
