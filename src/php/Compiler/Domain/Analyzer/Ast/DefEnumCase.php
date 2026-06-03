<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

/**
 * A single case of a `defenum*`: the keyword-derived case name and its
 * optional backing scalar value (`int`/`string`, or `null` for a pure enum).
 */
final readonly class DefEnumCase
{
    public function __construct(
        private string $name,
        private int|string|null $value,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): int|string|null
    {
        return $this->value;
    }
}
