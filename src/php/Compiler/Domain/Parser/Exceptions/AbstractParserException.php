<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\Exceptions;

use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Phel\Lang\SourceLocation;

abstract class AbstractParserException extends AbstractLocatedException
{
    public function __construct(
        string $message,
        private readonly CodeSnippet $codeSnippet,
        SourceLocation $startLocation,
        SourceLocation $endLocation,
    ) {
        parent::__construct($message, $startLocation, $endLocation);
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
