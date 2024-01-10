<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ReadModel;

use Phel\Lang\TypeInterface;

final readonly class ReaderResult
{
    public function __construct(
        private float|bool|int|string|TypeInterface|null $ast,
        private CodeSnippet $codeSnippet,
    ) {
    }

    public function getAst(): float|bool|int|string|TypeInterface|null
    {
        return $this->ast;
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
