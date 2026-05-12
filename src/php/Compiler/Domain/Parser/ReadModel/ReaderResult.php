<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ReadModel;

use Phel\Shared\Parser\ReadModel\CodeSnippet;

final readonly class ReaderResult
{
    public function __construct(
        private mixed $ast,
        private CodeSnippet $codeSnippet,
    ) {}

    public function getAst(): mixed
    {
        return $this->ast;
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
