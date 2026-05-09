<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

interface BindingDeconstructorInterface
{
    /**
     * Destructure a symbol $binding and add the result to $bindings.
     *
     * @param list<array{0: Symbol, 1: mixed}>         $bindings A reference to already defined bindings
     * @param mixed                                    $binding  The binding form
     * @param bool|float|int|string|TypeInterface|null $value    The value form
     */
    public function deconstruct(array &$bindings, mixed $binding, $value): void;
}
