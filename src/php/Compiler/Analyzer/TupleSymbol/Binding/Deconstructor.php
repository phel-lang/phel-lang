<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol\Binding;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor\BindingDeconstructorInterface;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor\NullBindingDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor\PhelArrayBindingDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor\SymbolBindingDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor\TableBindingDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor\TupleBindingDeconstructor;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\AbstractType;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;

final class Deconstructor implements DeconstructorInterface
{
    private BindingValidatorInterface $bindingValidator;

    public function __construct(BindingValidatorInterface $bindingChecker)
    {
        $this->bindingValidator = $bindingChecker;
    }

    public function deconstructTuple(Tuple $tuple): array
    {
        $bindings = [];

        for ($i = 0, $iMax = count($tuple); $i < $iMax; $i += 2) {
            $this->deconstruct($bindings, $tuple[$i], $tuple[$i + 1]);
        }

        return $bindings;
    }

    /**
     * Destructure a $binding $value pair and add the result to $bindings.
     *
     * @param array $bindings A reference to already defined bindings
     * @param AbstractType|string|float|int|bool|null $binding The binding form
     * @param AbstractType|string|float|int|bool|null $value The value form
     *
     * @throws AnalyzerException
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        $this->bindingValidator->assertSupportedBinding($binding);

        $this->createDeconstructorForBinding($binding)
            ->deconstruct($bindings, $binding, $value);
    }

    /**
     * @param AbstractType|string|float|int|bool|null $binding The binding form
     */
    private function createDeconstructorForBinding($binding): BindingDeconstructorInterface
    {
        if ($binding instanceof Symbol) {
            return new SymbolBindingDeconstructor();
        }

        if ($binding instanceof Tuple) {
            return new TupleBindingDeconstructor($this);
        }

        if ($binding instanceof Table) {
            return new TableBindingDeconstructor($this);
        }

        if ($binding instanceof PhelArray) {
            return new PhelArrayBindingDeconstructor($this);
        }

        return new NullBindingDeconstructor();
    }
}
