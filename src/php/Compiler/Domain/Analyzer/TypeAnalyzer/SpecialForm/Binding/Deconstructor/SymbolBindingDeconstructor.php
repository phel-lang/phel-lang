<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

final class SymbolBindingDeconstructor implements BindingDeconstructorInterface
{
    /**
     * @param list<array{0: Symbol, 1: mixed}>         $bindings
     * @param Symbol                                   $binding  The binding form
     * @param bool|float|int|string|TypeInterface|null $value    The value form
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
