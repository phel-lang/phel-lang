<?php

namespace Phel\Stream;

use Phel\Stream\SourceLocation;

class CodeSnippet {

    /**
     * @var SourceLocation | null
     */
    private $startLocation;

    /**
     * @var SourceLocation | null
     */
    private $endLocation;

    /**
     * @var string
     */
    private $code;

    public function __construct($startLocation, $endLocation, string $code)
    {
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
        $this->code = $code;
    }

    public function getStartLocation() {
        return $this->startLocation;
    }

    public function getEndLocation() {
        return $this->endLocation;
    }

    public function getCode() {
        return $this->code;
    }
}