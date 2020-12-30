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
    public const FIRST_SYMBOL_NAME = 'first';
    public const NEXT_SYMBOL_NAME = 'next';
    public const REST_SYMBOL_NAME = '&';

    private const STATE_START = 'start';
    private const STATE_REST = 'rest';
    private const STATE_DONE = 'done';

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
        $state = self::STATE_START;

        foreach ($binding as $current) {
            switch ($state) {
                case self::STATE_START:
                    if ($this->isRest($current)) {
                        $state = self::STATE_REST;
                        continue 2;
                    }
                    $accessSym = Symbol::gen()->copyLocationFrom($current);
                    $accessValue = $this->createTupleWithSymbol(self::FIRST_SYMBOL_NAME, $lastListSym, $current);
                    $bindings[] = [$accessSym, $accessValue];

                    $nextSym = Symbol::gen()->copyLocationFrom($current);
                    $nextValue = $this->createTupleWithSymbol(self::NEXT_SYMBOL_NAME, $lastListSym, $current);
                    $bindings[] = [$nextSym, $nextValue];
                    $lastListSym = $nextSym;

                    $this->tupleDeconstructor->deconstructBindings($bindings, $current, $accessSym);
                    break;
                case self::STATE_REST:
                    $state = self::STATE_DONE;
                    $accessSym = Symbol::gen()->copyLocationFrom($current);
                    $bindings[] = [$accessSym, $lastListSym];
                    $this->tupleDeconstructor->deconstructBindings($bindings, $current, $accessSym);
                    break;
                case self::STATE_DONE:
                    throw AnalyzerException::withLocation(
                        'Unsupported binding form, only one symbol can follow the & parameter',
                        $binding
                    );
            }
        }
    }

    /**
     * @param mixed $current
     */
    private function isRest($current): bool
    {
        return $current instanceof Symbol
            && $current->getName() === self::REST_SYMBOL_NAME;
    }

    /**
     * @param mixed $current
     */
    private function createTupleWithSymbol(string $symbolName, Symbol $lastListSym, $current): Tuple
    {
        return Tuple::create(
            (Symbol::create($symbolName))
                ->copyLocationFrom($current),
            $lastListSym
        )->copyLocationFrom($current);
    }
}
