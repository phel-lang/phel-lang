<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol\Binding;

use Phel\Exceptions\AnalyzerException;
use Phel\Lang\AbstractType;
use Phel\Lang\Tuple;

interface DeconstructorInterface
{
    /**
     * Deconstruct the binding forms from the tuple.
     */
    public function deconstructTuple(Tuple $tuple): array;

    /**
     * Deconstruct a $binding $value pair and add the result to $bindings.
     *
     * @param array $bindings A reference to already defined bindings
     * @param AbstractType|string|float|int|bool|null $binding The binding form
     * @param AbstractType|string|float|int|bool|null $value The value form
     *
     * @throws AnalyzerException
     */
    public function deconstruct(array &$bindings, $binding, $value): void;
}
