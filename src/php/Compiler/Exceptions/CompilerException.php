<?php

declare(strict_types=1);

namespace Phel\Compiler\Exceptions;

use Exception;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;

final class CompilerException extends Exception
{
    private CodeSnippet $codeSnippet;

    private AbstractLocatedException $nestedException;

    public function __construct(AbstractLocatedException $nestedException, CodeSnippet $codeSnippet)
    {
        parent::__construct($nestedException->getMessage());
        $this->nestedException = $nestedException;
        $this->codeSnippet = $codeSnippet;
    }

    public function getNestedException(): AbstractLocatedException
    {
        return $this->nestedException;
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
