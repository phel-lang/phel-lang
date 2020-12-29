<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol\Binding;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor\BindingDeconstructorInterface;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor\NullBindingDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor\PhelArrayBindingDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor\SymbolBindingDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor\TableBindingDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor\TupleBindingDeconstructor;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\AbstractType;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;

/**
 * @implements DeconstructorInterface<Tuple>
 */
final class TupleDeconstructor implements DeconstructorInterface
{
    private BindingValidatorInterface $bindingValidator;

    public function __construct(BindingValidatorInterface $bindingChecker)
    {
        $this->bindingValidator = $bindingChecker;
    }

    /**
     * @param Tuple $form
     */
    public function deconstruct($form): array
    {
        $bindings = [];

        for ($i = 0, $iMax = count($form); $i < $iMax; $i += 2) {
            $this->deconstructBindings($bindings, $form[$i], $form[$i + 1]);
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
    public function deconstructBindings(array &$bindings, $binding, $value): void
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
