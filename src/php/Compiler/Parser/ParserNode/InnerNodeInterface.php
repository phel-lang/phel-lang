<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ParserNode;

interface InnerNodeInterface extends NodeInterface
{
    /**
     * Returns all children of this node.
     *
     * @return NodeInterface[]
     */
    public function getChildren(): array;

    /**
     * @param NodeInterface[] $children
     */
    public function replaceChildren(array $children): self;

    public function getCodePrefix(): string;

    public function getCodePostfix(): ?string;
}
