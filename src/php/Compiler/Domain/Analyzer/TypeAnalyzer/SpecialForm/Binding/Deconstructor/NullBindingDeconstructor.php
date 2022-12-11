<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

/**
 * @template-implements BindingDeconstructorInterface<null>
 */
final class NullBindingDeconstructor implements BindingDeconstructorInterface
{
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        // Intentionally blank
    }
}
