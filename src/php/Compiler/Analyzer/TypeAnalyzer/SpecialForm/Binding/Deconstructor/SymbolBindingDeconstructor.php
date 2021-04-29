<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

/**
 * @implements BindingDeconstructorInterface<Symbol>
 */
final class SymbolBindingDeconstructor implements BindingDeconstructorInterface
{
    /**
     * @param Symbol $binding The binding form
     * @param TypeInterface|string|float|int|bool|null $value The value form
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        if ($this->shouldCreateSymbolFromBinding($binding)) {
            $symbol = Symbol::gen()->copyLocationFrom($binding);
            $bindings[] = [$symbol, $value];
        } else {
            $bindings[] = [$binding, $value];
        }
    }

    private function shouldCreateSymbolFromBinding(Symbol $binding): bool
    {
        return $binding->getName() === '_';
    }
}
