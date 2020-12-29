<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol\Binding;

/**
 * @psalm-template T
 */
interface DeconstructorInterface
{
    /**
     * Deconstruct the form's bindings.
     *
     * @param T $form
     *
     * @return array<mixed,mixed>
     */
    public function deconstruct($form): array;
}
