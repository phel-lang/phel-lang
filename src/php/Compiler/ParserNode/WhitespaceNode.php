<?php

namespace Phel\Compiler\ParserNode;

use Phel\Lang\SourceLocation;

final class WhitespaceNode implements TriviaNodeInterface
{
    private string $code;
    private SourceLocation $startLocation;
    private SourceLocation $endLocation;

    public function __construct(string $code, SourceLocation $startLocation, SourceLocation $endLocation)
    {
        $this->code = $code;
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
    }


    public function getCode(): string
    {
        return $this->code;
    }

    public function getStartLocation(): SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): SourceLocation
    {
        return $this->endLocation;
    }
}
