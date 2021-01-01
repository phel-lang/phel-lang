<?php

declare(strict_types=1);

namespace Phel\Exceptions;

use Exception;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;

final class CompilerException extends Exception
{
    private CodeSnippet $codeSnippet;

    private PhelCodeException $nestedException;

    public function __construct(PhelCodeException $nestedException, CodeSnippet $codeSnippet)
    {
        parent::__construct($nestedException->getMessage(), 0, null);
        $this->nestedException = $nestedException;
        $this->codeSnippet = $codeSnippet;
    }

    public function getNestedException(): PhelCodeException
    {
        return $this->nestedException;
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
