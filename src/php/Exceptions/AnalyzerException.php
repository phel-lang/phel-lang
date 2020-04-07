<?php

namespace Phel\Exceptions;

use Exception;
use Phel\Stream\SourceLocation;

class AnalyzerException extends Exception {

    /**
     * @var SourceLocation
     */
    private $startLocation;

    /**
     * @var SourceLocation
     */
    private $endLocation;

    public function __construct($message, $startLocation = null, $endLocation = null)
    {
        parent::__construct($message);
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
    }

    public function getStartLocation() {
        return $this->startLocation;
    }

    public function getEndLocation() {
        return $this->endLocation;
    }
}