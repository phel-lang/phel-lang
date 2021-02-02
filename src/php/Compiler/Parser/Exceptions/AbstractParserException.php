<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\Exceptions;

use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Lang\SourceLocation;

abstract class AbstractParserException extends AbstractLocatedException
{
    private CodeSnippet $codeSnippet;

    public function __construct(
        string $message,
        CodeSnippet $codeSnippet,
        SourceLocation $startLocation,
        SourceLocation $endLocation
    ) {
        parent::__construct($message, $startLocation, $endLocation);
        $this->codeSnippet = $codeSnippet;
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
