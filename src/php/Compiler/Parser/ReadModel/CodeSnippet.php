<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ReadModel;

use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Lang\SourceLocation;

final class CodeSnippet
{
    private SourceLocation $startLocation;
    private SourceLocation $endLocation;
    private string $code;

    public function __construct(
        SourceLocation $startLocation,
        SourceLocation $endLocation,
        string $code
    ) {
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
        $this->code = $code;
    }

    public static function fromNode(NodeInterface $node): self
    {
        return new self(
            $node->getStartLocation(),
            $node->getEndLocation(),
            $node->getCode()
        );
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
