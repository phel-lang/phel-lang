<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding;

use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\BindingDeconstructorInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\MapBindingDeconstructor;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\NullBindingDeconstructor;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\SymbolBindingDeconstructor;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\VectorBindingDeconstructor;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

use function count;

final readonly class Deconstructor implements DeconstructorInterface
{
    public function __construct(
        private BindingValidatorInterface $bindingValidator,
    ) {
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
     * @param float|bool|int|string|TypeInterface|null $binding The binding form
     * @param float|bool|int|string|TypeInterface|null $value The value form
     *
     * @throws AnalyzerException
     */
    public function deconstructBindings(
        array &$bindings,
        float|bool|int|string|TypeInterface|null $binding,
        float|bool|int|string|TypeInterface|null $value,
    ): void {
        $this->bindingValidator->assertSupportedBinding($binding);

        $this->createDeconstructorForBinding($binding)
            ->deconstruct($bindings, $binding, $value);
    }

    /**
     * @param float|bool|int|string|TypeInterface|null $binding The binding form
     */
    private function createDeconstructorForBinding(float|bool|int|string|TypeInterface|null $binding): BindingDeconstructorInterface
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
