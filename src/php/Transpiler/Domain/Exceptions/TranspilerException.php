<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Exceptions;

use Phel\Transpiler\Domain\Parser\ReadModel\CodeSnippet;
use RuntimeException;

final class TranspilerException extends RuntimeException
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
