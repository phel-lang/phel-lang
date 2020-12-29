<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Lang\AbstractType;
use Phel\Lang\Symbol;

/**
 * @implements BindingDeconstructorInterface<Symbol>
 */
final class SymbolBindingDeconstructor implements BindingDeconstructorInterface
{
    /**
     * @param Symbol $binding The binding form
     * @param AbstractType|string|float|int|bool|null $value The value form
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        if ($binding->getName() === '_') {
            $s = Symbol::gen()->copyLocationFrom($binding);
            $bindings[] = [$s, $value];
        } else {
            $bindings[] = [$binding, $value];
        }
    }
}
