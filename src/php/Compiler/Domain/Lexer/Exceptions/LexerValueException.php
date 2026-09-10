<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Lexer\Exceptions;

use Phel\Lang\SourceLocation;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\ReadModel\CodeSnippet;

use function sprintf;

/**
 * A located error, like every other compile error, so `phel run`, `phel eval`
 * and the REPL report it the way they report a parser or a reader error:
 * `[PHEL310]`, the user's file and line, the offending line, and a caret under
 * the character the lexer stopped on. It used to be a bare `RuntimeException`,
 * which left the CLI dumping the exception class and pointing its `at` line at
 * this file (#3289).
 *
 * @internal
 */
final class LexerValueException extends AbstractLocatedException
{
    private function __construct(
        string $message,
        private readonly CodeSnippet $codeSnippet,
        SourceLocation $startLocation,
        SourceLocation $endLocation,
    ) {
        parent::__construct($message, $startLocation, $endLocation);
        $this->setErrorCode(ErrorCode::LEXER_ERROR);
    }

    public static function unexpectedLexerState(
        string $character,
        CodeSnippet $codeSnippet,
        SourceLocation $startLocation,
        SourceLocation $endLocation,
    ): self {
        return new self(
            sprintf("Cannot lex '%s': no token starts with it.", $character),
            $codeSnippet,
            $startLocation,
            $endLocation,
        );
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
