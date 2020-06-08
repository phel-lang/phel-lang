<?php

namespace Phel\Exceptions;

use Exception;
use Phel\CodeSnippet;

class CompilerException extends Exception
{
    private CodeSnippet $codeSnippet;

    public function __construct(PhelCodeException $nestedException, CodeSnippet $codeSnippet)
    {
        parent::__construct("", 0, $nestedException);
        $this->codeSnippet = $codeSnippet;
    }

    public function getCodeSnippet()
    {
        return $this->codeSnippet;
    }
}
