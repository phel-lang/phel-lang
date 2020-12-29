<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor;

final class NullBindingDeconstructor implements BindingDeconstructorInterface
{
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        // Intentionally blank
    }
}
