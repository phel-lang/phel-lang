<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ParserNode;

use Phel\Lang\SourceLocation;

/**
 * @template T
 */
abstract class AbstractAtomNode implements NodeInterface
{
    /**
     * @param string $code The code of the node
     * @param SourceLocation $startLocation The start location of the atom
     * @param SourceLocation $endLocation The end location of the atom
     * @param T $value The value of the atom
     */
    public function __construct(
        private readonly string $code,
        private readonly SourceLocation $startLocation,
        private readonly SourceLocation $endLocation,
        private readonly mixed $value,
    ) {
    }

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
