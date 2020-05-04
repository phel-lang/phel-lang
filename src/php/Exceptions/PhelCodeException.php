<?php

namespace Phel\Exceptions;

use Exception;
use Phel\Stream\SourceLocation;

class PhelCodeException extends Exception {

    /**
     * @var SourceLocation
     */
    private $startLocation;

    /**
     * @var SourceLocation
     */
    private $endLocation;

    public function __construct($message, $startLocation, $endLocation, $nestedException = null)
    {
        parent::__construct($message, 0, $nestedException);
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