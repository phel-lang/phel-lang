<?php

namespace Phel;

use Phel\Lang\Phel;
use Phel\Stream\CodeSnippet;

class ReaderResult {

    /**
     * @var Phel|scalar|null
     */
    private $ast;

    /**
     * @var CodeSnippet
     */
    private $codeSnippet;

    /**
     * Constructor
     * 
     * @param Phel|scalar|null $ast The form read by the reader
     * @param CodeSnippet $codeSnippet The Code that have been read for the form.
     */
    public function __construct($ast, CodeSnippet $codeSnippet)
    {
        $this->ast = $ast;
        $this->codeSnippet = $codeSnippet;
    }

    /**
     * @return Phel|scalar|null
     */
    public function getAst() {
        return $this->ast;
    }

    public function getCodeSnippet(): CodeSnippet {
        return $this->codeSnippet;
    }
}