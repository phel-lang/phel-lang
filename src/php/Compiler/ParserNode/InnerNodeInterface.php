<?php

namespace Phel\Compiler\ParserNode;

interface InnerNodeInterface extends NodeInterface
{

    /**
     * Returns all children of this node.
     *
     * @return NodeInterface[]
     */
    public function getChildren(): array;
}
