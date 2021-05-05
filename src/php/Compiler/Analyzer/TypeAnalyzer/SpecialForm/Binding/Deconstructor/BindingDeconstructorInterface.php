<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

use Phel\Lang\TypeInterface;

/**
 * @psalm-template T
 */
interface BindingDeconstructorInterface
{
    /**
     * Destructure a symbol $binding and add the result to $bindings.
     *
     * @param array $bindings A reference to already defined bindings
     * @param T $binding The binding form
     * @param TypeInterface|string|float|int|bool|null $value The value form
     */
    public function deconstruct(array &$bindings, $binding, $value): void;
}
