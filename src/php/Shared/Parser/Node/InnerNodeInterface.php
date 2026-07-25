<?php

declare(strict_types=1);

namespace Phel\Shared\Parser\Node;

interface InnerNodeInterface extends NodeInterface
{
    /**
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
