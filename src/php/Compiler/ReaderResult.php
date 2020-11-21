<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Lang\AbstractType;

final class ReaderResult
{
    /** @var AbstractType|string|float|int|bool|null */
    private $ast;
    private CodeSnippet $codeSnippet;

    /**
     * @param AbstractType|string|float|int|bool|null $ast The form read by the reader
     * @param CodeSnippet $codeSnippet The Code that have been read for the form
     */
    public function __construct($ast, CodeSnippet $codeSnippet)
    {
        $this->ast = $ast;
        $this->codeSnippet = $codeSnippet;
    }

    /**
     * @return AbstractType|string|float|int|bool|null
     */
    public function getAst()
    {
        return $this->ast;
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
