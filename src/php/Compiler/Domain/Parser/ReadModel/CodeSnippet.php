<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ReadModel;

use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Lang\SourceLocation;

final readonly class CodeSnippet
{
    public function __construct(
        private SourceLocation $startLocation,
        private SourceLocation $endLocation,
        private string $code,
    ) {
    }

    public static function fromNode(NodeInterface $node): self
    {
        return new self(
            $node->getStartLocation(),
            $node->getEndLocation(),
            $node->getCode(),
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
