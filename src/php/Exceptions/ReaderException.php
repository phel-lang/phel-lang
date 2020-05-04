<?php

namespace Phel\Exceptions;

use Phel\Stream\CodeSnippet;

class ReaderException extends PhelCodeException {

    /**
     * @var CodeSnippet
     */
    private $codeSnippet;

    public function __construct($message, $startLocation, $endLocation, $codeSnippet, $nestedException = null) {
        parent::__construct($message, $startLocation, $endLocation, $nestedException);
        $this->codeSnippet = $codeSnippet;
    }

    public function getCodeSnippet() {
        return $this->codeSnippet;
    }
}