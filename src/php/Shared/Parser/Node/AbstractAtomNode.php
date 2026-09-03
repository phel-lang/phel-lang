<?php

declare(strict_types=1);

namespace Phel\Shared\Parser\Node;

use Phel\Lang\SourceLocation;

/**
 * @template-covariant T
 */
abstract class AbstractAtomNode implements NodeInterface
{
    /**
     * @param T $value
     */
    public function __construct(
        private readonly string $code,
        private readonly SourceLocation $startLocation,
        private readonly SourceLocation $endLocation,
        private readonly mixed $value,
    ) {}

    public function getCode(): string
    {
        return $this->code;
    }

    public function getStartLocation(): SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): SourceLocation
    {
        return $this->endLocation;
    }

    /**
     * @return T
     */
    public function getValue()
    {
        return $this->value;
    }
}
