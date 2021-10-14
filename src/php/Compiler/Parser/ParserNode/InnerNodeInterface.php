<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ParserNode;

interface InnerNodeInterface extends NodeInterface
{
    /**
     * Returns all children of this node.
     *
     * @return list<NodeInterface>
     */
    public function getChildren(): array;

    /**
     * @param list<NodeInterface> $children
     */
    public function replaceChildren(array $children): self;

    public function getCodePrefix(): string;

    public function getCodePostfix(): ?string;
}
