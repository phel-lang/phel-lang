<?php

namespace Phel\Compiler\Parser\ParserNode;

use Phel\Lang\SourceLocation;

final class FileNode implements InnerNodeInterface
{
    /** @var NodeInterface[] */
    private array $children;
    private SourceLocation $startLocation;
    private SourceLocation $endLocation;

    public function __construct(SourceLocation $startLocation, SourceLocation $endLocation, array $children)
    {
        $this->children = $children;
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
    }

    /**
     * @param NodeInterface[] $children
     */
    public static function createFromChildren(array $children): self
    {
        if (count($children) > 0) {
            $startLocation = $children[0]->getStartLocation();
            $endLocation = $children[count($children) - 1]->getEndLocation();
        } else {
            $startLocation = new SourceLocation('', 0, 0);
            $endLocation = new SourceLocation('', 0, 0);
        }

        return new self($startLocation, $endLocation, $children);
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function replaceChildren($children): InnerNodeInterface
    {
        $this->children = $children;
        return $this;
    }

    public function getCode(): string
    {
        $code = '';
        foreach ($this->children as $child) {
            $code .= $child->getCode();
        }

        return $code;
    }

    public function getCodePrefix(): string
    {
        return '';
    }

    public function getCodePostfix(): ?string
    {
        return '';
    }

    public function getStartLocation(): SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): SourceLocation
    {
        return $this->endLocation;
    }
}
