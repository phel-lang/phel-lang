<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding;

use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\BindingDeconstructorInterface;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\MapBindingDeconstructor;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\NullBindingDeconstructor;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\SymbolBindingDeconstructor;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\VectorBindingDeconstructor;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

final class Deconstructor implements DeconstructorInterface
{
    private BindingValidatorInterface $bindingValidator;

    public function __construct(BindingValidatorInterface $bindingChecker)
    {
        $this->bindingValidator = $bindingChecker;
    }

    public function deconstruct(PersistentVectorInterface $form): array
    {
        $bindings = [];

        for ($i = 0, $iMax = count($form); $i < $iMax; $i += 2) {
            $this->deconstructBindings($bindings, $form->get($i), $form->get($i + 1));
        }

        return $bindings;
    }

    /**
     * Destructure a $binding $value pair and add the result to $bindings.
     *
     * @param array $bindings A reference to already defined bindings
     * @param TypeInterface|string|float|int|bool|null $binding The binding form
     * @param TypeInterface|string|float|int|bool|null $value The value form
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
     * @param TypeInterface|string|float|int|bool|null $binding The binding form
     */
    private function createDeconstructorForBinding($binding): BindingDeconstructorInterface
    {
        if ($binding instanceof Symbol) {
            return new SymbolBindingDeconstructor();
        }

        if ($binding instanceof PersistentVectorInterface) {
            return new VectorBindingDeconstructor($this);
        }

        if ($binding instanceof PersistentMapInterface) {
            return new MapBindingDeconstructor($this);
        }

        return new NullBindingDeconstructor();
    }
}
