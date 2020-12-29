<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\AbstractType;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

/**
 * @implements BindingDeconstructorInterface<Tuple>
 */
final class TupleBindingDeconstructor implements BindingDeconstructorInterface
{
    private TupleDeconstructor $tupleDeconstructor;

    public function __construct(TupleDeconstructor $deconstructor)
    {
        $this->tupleDeconstructor = $deconstructor;
    }

    /**
     * @param Tuple $binding The binding form
     * @param AbstractType|string|float|int|bool|null $value The value form
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        $arrSymbol = Symbol::gen()->copyLocationFrom($binding);

        $bindings[] = [$arrSymbol, $value];
        $lastListSym = $arrSymbol;
        $state = 'start';

        foreach ($binding as $current) {
            switch ($state) {
                case 'start':
                    if ($current instanceof Symbol && $current->getName() === '&') {
                        $state = 'rest';
                    } else {
                        $accessSym = Symbol::gen()->copyLocationFrom($current);
                        $accessValue = Tuple::create(
                            (Symbol::create('first'))->copyLocationFrom($current),
                            $lastListSym
                        )->copyLocationFrom($current);
                        $bindings[] = [$accessSym, $accessValue];

                        $nextSym = Symbol::gen()->copyLocationFrom($current);
                        $nextValue = Tuple::create(
                            (Symbol::create('next'))->copyLocationFrom($current),
                            $lastListSym
                        )->copyLocationFrom($current);
                        $bindings[] = [$nextSym, $nextValue];
                        $lastListSym = $nextSym;

                        $this->tupleDeconstructor->deconstructBindings($bindings, $current, $accessSym);
                    }
                    break;
                case 'rest':
                    $state = 'done';
                    $accessSym = Symbol::gen()->copyLocationFrom($current);
                    $bindings[] = [$accessSym, $lastListSym];
                    $this->tupleDeconstructor->deconstructBindings($bindings, $current, $accessSym);
                    break;
                case 'done':
                    throw AnalyzerException::withLocation(
                        'Unsupported binding form, only one symbol can follow the & parameter',
                        $binding
                    );
            }
        }
    }
}
