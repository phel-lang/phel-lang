<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Lang\Symbol;

/**
 * A typed class/interface constant declared via a `:php/const` block, e.g.
 * `(^{:tag int} MAX 100)`. The name symbol carries the optional `:tag` type;
 * the value is a compile-time scalar (int/float/string/bool/nil).
 */
final readonly class PhpClassConst
{
    public function __construct(
        private Symbol $name,
        private int|float|string|bool|null $value,
    ) {}

    public function getName(): Symbol
    {
        return $this->name;
    }

    public function getValue(): int|float|string|bool|null
    {
        return $this->value;
    }
}
