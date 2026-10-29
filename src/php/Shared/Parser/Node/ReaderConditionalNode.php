<?php

declare(strict_types=1);

namespace Phel\Shared\Parser\Node;

use LogicException;
use Phel\Lang\SourceLocation;

final class ReaderConditionalNode implements InnerNodeInterface
{
    private readonly string $selectedCode;

    /** @var list<NodeInterface> */
    private array $children;

    public function __construct(
        private readonly string $code,
        NodeInterface $selected,
        private readonly SourceLocation $startLocation,
        private readonly SourceLocation $endLocation,
        private readonly string $prefix,
        private readonly string $suffix,
    ) {
        $this->selectedCode = $selected->getCode();
        $this->children = [$selected];
    }

    public function getSelected(): NodeInterface
    {
        foreach ($this->children as $child) {
            if (!$child instanceof TriviaNodeInterface) {
                return $child;
            }
        }

        throw new LogicException('Reader conditional has no selected form');
    }

    public function getCode(): string
    {
        $selectedCode = implode('', array_map(static fn(NodeInterface $node): string => $node->getCode(), $this->children));
        return $selectedCode === $this->selectedCode ? $this->code : $this->prefix . $selectedCode . $this->suffix;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function replaceChildren(array $children): InnerNodeInterface
    {
        $this->children = $children;
        return $this;
    }

    public function getCodePrefix(): string
    {
        return $this->prefix;
    }

    public function getCodePostfix(): string
    {
        return $this->suffix;
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
