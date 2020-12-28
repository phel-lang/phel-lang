<?php

namespace Phel\Compiler\ParserNode;

use Phel\Lang\SourceLocation;

interface NodeInterface
{

    /**
     * Convert the node to a printable string.
     */
    public function getCode(): string;

    public function getStartLocation(): SourceLocation;

    public function getEndLocation(): SourceLocation;
}
