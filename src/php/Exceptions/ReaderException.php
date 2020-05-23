<?php

namespace Phel\Exceptions;

use Exception;
use Phel\CodeSnippet;
use Phel\Lang\SourceLocation;

class ReaderException extends PhelCodeException {

    /**
     * @var CodeSnippet
     */
    private $codeSnippet;

    public function __construct(
        string $message, 
        SourceLocation $startLocation, 
        SourceLocation $endLocation, 
        CodeSnippet $codeSnippet, 
        ?Exception $nestedException = null
    ) {
        parent::__construct($message, $startLocation, $endLocation, $nestedException);
        $this->codeSnippet = $codeSnippet;
    }

    public function getCodeSnippet(): CodeSnippet {
        return $this->codeSnippet;
    }
}