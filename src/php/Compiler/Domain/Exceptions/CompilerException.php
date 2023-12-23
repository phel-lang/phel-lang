<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Exceptions;

use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use RuntimeException;

final class CompilerException extends RuntimeException
{
    public function __construct(
        private readonly AbstractLocatedException $nestedException,
        private readonly CodeSnippet $codeSnippet,
    ) {
        parent::__construct($nestedException->getMessage());
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
