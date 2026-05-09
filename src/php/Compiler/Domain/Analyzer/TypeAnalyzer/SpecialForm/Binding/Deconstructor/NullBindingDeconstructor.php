<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

use Phel\Lang\Symbol;

final class NullBindingDeconstructor implements BindingDeconstructorInterface
{
    /**
     * @param list<array{0: Symbol, 1: mixed}> $bindings
     * @param mixed                            $binding
     * @param mixed                            $value
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        // Intentionally blank
    }
}
