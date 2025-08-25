<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ParserNode;

use Phel\Lang\SourceLocation;

final readonly class CommentMacroNode implements TriviaNodeInterface
{
    public function __construct(
        private NodeInterface $node,
        private SourceLocation $startLocation,
    ) {
    }

    public function getNode(): NodeInterface
    {
        return $this->node;
    }

    public function getCode(): string
    {
        return '#_' . $this->node->getCode();
    }

    public function getStartLocation(): SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): SourceLocation
    {
        return $this->node->getEndLocation();
    }
}
