<?php

declare(strict_types=1);

namespace Phel\Shared\Parser\Node;

use Phel\Lang\SourceLocation;

/**
 * Marker node for #?@() reader conditional splicing.
 * Wraps children that should be spliced into the parent collection.
 */
final readonly class ReaderCondSplicingNode implements NodeInterface
{
    /**
     * @param list<NodeInterface> $children
     */
    public function __construct(
        private array $children,
        private SourceLocation $startLocation,
        private SourceLocation $endLocation,
        private ?string $code = null,
        private ?ReaderConditionalNode $source = null,
    ) {}

    /**
     * @return list<NodeInterface>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function getSelected(): ?NodeInterface
    {
        return $this->source?->getSelected();
    }

    public function getCode(): string
    {
        if ($this->source instanceof ReaderConditionalNode) {
            return $this->source->getCode();
        }

        if ($this->code !== null) {
            return $this->code;
        }

        $code = '';
        foreach ($this->children as $child) {
            $code .= $child->getCode();
        }

        return $code;
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
