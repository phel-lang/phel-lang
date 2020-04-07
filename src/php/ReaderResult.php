<?php

namespace Phel;

use Phel\Lang\Phel;
use Phel\Stream\SourceLocation;

class ReaderResult {

    /**
     * @var Phel | null
     */
    private $ast;

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

    public function __construct($ast, $startLocation, $endLocation, string $code)
    {
        $this->ast = $ast;
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
        $this->code = $code;
    }

    public function getAst() {
        return $this->ast;
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