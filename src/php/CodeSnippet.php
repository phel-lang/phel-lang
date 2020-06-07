<?php

namespace Phel;

use Phel\Lang\SourceLocation;

class CodeSnippet
{

    /**
     * @var SourceLocation
     */
    private $startLocation;

    /**
     * @var SourceLocation
     */
    private $endLocation;

    /**
     * @var string
     */
    private $code;

    public function __construct(SourceLocation $startLocation, SourceLocation $endLocation, string $code)
    {
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
        $this->code = $code;
    }

    public function getStartLocation(): SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): SourceLocation
    {
        return $this->endLocation;
    }

    public function getCode(): string
    {
        return $this->code;
    }
}
