<?php

declare(strict_types=1);

namespace Phel\Compiler\Exceptions;

use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use RuntimeException;

final class CompilerException extends RuntimeException
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
