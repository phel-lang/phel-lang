<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;

/**
 * @implements BindingDeconstructorInterface<PersistentVectorInterface<mixed>>
 */
final class VectorBindingDeconstructor implements BindingDeconstructorInterface
{
    public const FIRST_SYMBOL_NAME = 'first';
    public const NEXT_SYMBOL_NAME = 'next';
    public const REST_SYMBOL_NAME = '&';

    private const STATE_START = 'start';
    private const STATE_REST = 'rest';
    private const STATE_DONE = 'done';

    private Deconstructor $deconstructor;
    private string $currentState = self::STATE_START;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private Symbol $currentListSymbol;

    public function __construct(Deconstructor $deconstructor)
    {
        $this->deconstructor = $deconstructor;
    }

    /**
     * @param mixed $binding
     * @param mixed $value
     *
     * @throws AbstractLocatedException
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        $arrSymbol = Symbol::gen()->copyLocationFrom($binding);
        $bindings[] = [$arrSymbol, $value];
        $this->currentListSymbol = $arrSymbol;

        foreach ($binding as $current) {
            switch ($this->currentState) {
                case self::STATE_START:
                    $this->stateStart($bindings, $current);
                    break;
                case self::STATE_REST:
                    $this->stateRest($bindings, $current);
                    break;
                case self::STATE_DONE:
                    $this->triggerUnsupportedBindingFormException($binding);
            }
        }
    }

    /**
     * @param mixed $current
     */
    private function stateStart(array &$bindings, $current): void
    {
        if ($this->isRest($current)) {
            $this->currentState = self::STATE_REST;
            return;
        }
        $accessSymbol = Symbol::gen()->copyLocationFrom($current);
        $accessValue = $this->createBindingValue(self::FIRST_SYMBOL_NAME, $current);
        $bindings[] = [$accessSymbol, $accessValue];

        $nextSymbol = Symbol::gen()->copyLocationFrom($current);
        $nextValue = $this->createBindingValue(self::NEXT_SYMBOL_NAME, $current);
        $bindings[] = [$nextSymbol, $nextValue];
        $this->currentListSymbol = $nextSymbol;

        $this->deconstructor->deconstructBindings($bindings, $current, $accessSymbol);
    }

    /**
     * @param mixed $current
     */
    private function stateRest(array &$bindings, $current): void
    {
        $this->currentState = self::STATE_DONE;
        $accessSymbol = Symbol::gen()->copyLocationFrom($current);
        $bindings[] = [$accessSymbol, $this->currentListSymbol];

        $this->deconstructor->deconstructBindings($bindings, $current, $accessSymbol);
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
    private function createBindingValue(string $symbolName, $current): PersistentListInterface
    {
        return TypeFactory::getInstance()->persistentListFromArray([
            (Symbol::create($symbolName))->copyLocationFrom($current),
            $this->currentListSymbol,
        ])->copyLocationFrom($current);
    }

    /**
     * @throws AnalyzerException
     */
    private function triggerUnsupportedBindingFormException(PersistentVectorInterface $binding): void
    {
        throw AnalyzerException::withLocation(
            sprintf(
                'Unsupported binding form, only one symbol can follow the %s parameter',
                self::REST_SYMBOL_NAME
            ),
            $binding
        );
    }
}
