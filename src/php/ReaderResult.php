<?php

namespace Phel;

use Phel\Lang\AbstractType;

class ReaderResult
{

    /**
     * @var AbstractType|scalar|null
     */
    private $ast;

    /**
     * @var CodeSnippet
     */
    private $codeSnippet;

    /**
     * Constructor
     *
     * @param AbstractType|scalar|null $ast The form read by the reader
     * @param CodeSnippet $codeSnippet The Code that have been read for the form.
     */
    public function __construct($ast, CodeSnippet $codeSnippet)
    {
        $this->ast = $ast;
        $this->codeSnippet = $codeSnippet;
    }

    /**
     * @return AbstractType|scalar|null
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
