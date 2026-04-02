<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ParserNode;

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
    ) {}

    /**
     * @return list<NodeInterface>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function getCode(): string
    {
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
