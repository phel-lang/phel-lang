<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\Exceptions;

use Phel\Lang\SourceLocation;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Throwable;

/**
 * Starts as `PARSER_ERROR`, the code `phel analyze` reports for a parser
 * error with no more specific one, so `phel run` prints it too (#3537).
 *
 * @internal
 */
abstract class AbstractParserException extends AbstractLocatedException
{
    public function __construct(
        string $message,
        private readonly CodeSnippet $codeSnippet,
        SourceLocation $startLocation,
        SourceLocation $endLocation,
        ?Throwable $nestedException = null,
    ) {
        parent::__construct($message, $startLocation, $endLocation, $nestedException);
        $this->setErrorCode(ErrorCode::PARSER_ERROR);
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
