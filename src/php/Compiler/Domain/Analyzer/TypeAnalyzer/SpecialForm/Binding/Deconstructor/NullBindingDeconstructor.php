<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\DeconstructorInterface;

/**
 * @phpstan-import-type BindingTuple from DeconstructorInterface
 */
final class NullBindingDeconstructor implements BindingDeconstructorInterface
{
    /**
     * @param list<BindingTuple> $bindings
     * @param mixed              $binding
     * @param mixed              $value
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        // Intentionally blank
    }
}
