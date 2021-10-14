<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ParserNode;

use Phel\Lang\SourceLocation;

final class MetaNode implements InnerNodeInterface
{
    private NodeInterface $meta;
    private SourceLocation $startLocation;
    private SourceLocation $endLocation;
    /** @var array<int, NodeInterface> */
    private array $children;

    public function __construct(
        NodeInterface $meta,
        SourceLocation $startLocation,
        SourceLocation $endLocation,
        array $children
    ) {
        $this->meta = $meta;
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
        $this->children = $children;
    }

    /**
     * @return list<NodeInterface>
     */
    public function getChildren(): array
    {
        return [$this->meta, ...$this->children];
    }

    /**
     * @param list<NodeInterface> $children
     */
    public function replaceChildren(array $children): InnerNodeInterface
    {
        $this->meta = $children[0];
        $this->children = array_slice($children, 1);

        return $this;
    }

    public function getCode(): string
    {
        $code = '';
        foreach ($this->children as $child) {
            $code .= $child->getCode();
        }

        return $this->getCodePrefix() . $this->meta->getCode() . $code;
    }

    public function getCodePrefix(): string
    {
        return '^';
    }

    public function getCodePostfix(): ?string
    {
        return null;
    }

    public function getStartLocation(): SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): SourceLocation
    {
        return $this->endLocation;
    }

    public function getMetaNode(): NodeInterface
    {
        return $this->meta;
    }

    public function getObjectNode(): NodeInterface
    {
        return $this->children[count($this->children) - 1];
    }
}
