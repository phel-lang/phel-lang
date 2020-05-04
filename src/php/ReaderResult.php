<?php

namespace Phel;

use Phel\Lang\Phel;
use Phel\Stream\CodeSnippet;

class ReaderResult {

    /**
     * @var Phel
     */
    private $ast;

    /**
     * @var CodeSnippet
     */
    private $codeSnippet;

    public function __construct($ast, $codeSnippet)
    {
        $this->ast = $ast;
        $this->codeSnippet = $codeSnippet;
    }

    public function getAst() {
        return $this->ast;
    }

    public function getCodeSnippet() {
        return $this->codeSnippet;
    }
}