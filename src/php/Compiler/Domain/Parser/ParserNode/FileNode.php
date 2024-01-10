<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ParserNode;

use Phel\Lang\SourceLocation;

use function count;

final class FileNode implements InnerNodeInterface
{
    /**
     * @param list<NodeInterface> $children
     */
    public function __construct(
        private readonly SourceLocation $startLocation,
        private readonly SourceLocation $endLocation,
        private array $children,
    ) {
    }

    /**
     * @param list<NodeInterface> $children
     */
    public static function createFromChildren(array $children): self
    {
        if ($children !== []) {
            $startLocation = $children[0]->getStartLocation();
            $endLocation = $children[count($children) - 1]->getEndLocation();
        } else {
            $startLocation = new SourceLocation('', 0, 0);
            $endLocation = new SourceLocation('', 0, 0);
        }

        return new self($startLocation, $endLocation, $children);
    }

    /**
     * @return list<NodeInterface>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @param list<NodeInterface> $children
     */
    public function replaceChildren(array $children): InnerNodeInterface
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
