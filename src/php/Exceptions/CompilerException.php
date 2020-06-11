<?php

namespace Phel\Exceptions;

use Exception;
use Phel\CodeSnippet;

class CompilerException extends Exception
{
    private CodeSnippet $codeSnippet;

    private PhelCodeException $nestedException;

    public function __construct(PhelCodeException $nestedException, CodeSnippet $codeSnippet)
    {
        parent::__construct("", 0, null);
        $this->nestedException = $nestedException;
        $this->codeSnippet = $codeSnippet;
    }

    public function getNestedException(): PhelCodeException
    {
        return $this->nestedException;
    }

    public function getCodeSnippet()
    {
        return $this->codeSnippet;
    }
}
