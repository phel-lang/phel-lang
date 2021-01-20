<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ParserNode;

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
