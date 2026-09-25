<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

use Phel;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\DeconstructorInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReturnTypeInferrer;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Destructure;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\AbstractLocatedException;

use function sprintf;

/**
 * Expands `[a b & r]` into bindings that read a vector by index and walk any
 * other value with `first`/`next` (#3356):
 *
 *     v  <value>
 *     iv (php/instanceof v PersistentVectorInterface)
 *     a  (if (php/=== iv true) (php/aget v 0) (first v))
 *     s1 (if (php/=== iv true) nil (next v))
 *     b  (if (php/=== iv true) (php/aget v 1) (first s1))
 *     s2 (if (php/=== iv true) nil (next s1))
 *     r  (if (php/=== iv true) (Destructure/nthNext v 2) s2)
 *
 * A vector never reads the walk, so its `sN` are `nil`. Everything else
 * (lists, lazy and infinite seqs, sets, maps, strings, PHP arrays, `nil`)
 * keeps the exact `first`/`next` calls in the same order, the trailing
 * `next` after the last element included. A vector's tail is what that many
 * `next` calls return: `(.cdr v)` after one position, `nthNext` after more.
 *
 * Every binding added here except `v` carries
 * {@see ReturnTypeInferrer::SYNTHETIC_BINDING}: the operators in them are
 * the compiler's, and must not count as the user's evidence for a return
 * type. `[]` and `[& r]` expand exactly as before this fast path.
 *
 * @phpstan-import-type BindingTuple from DeconstructorInterface
 *
 * @internal
 */
final class VectorBindingDeconstructor implements BindingDeconstructorInterface
{
    public const string FIRST_SYMBOL_NAME = 'first';

    public const string NEXT_SYMBOL_NAME = 'next';

    public const string REST_SYMBOL_NAME = '&';

    private const string INSTANCEOF_SYMBOL_NAME = 'php/instanceof';

    private const string IDENTICAL_SYMBOL_NAME = 'php/===';

    private const string CDR_METHOD_NAME = 'cdr';

    private const string INDEXED_CLASS_NAME = '\\' . PersistentVectorInterface::class;

    private const string DESTRUCTURE_CLASS_NAME = '\\' . Destructure::class;

    private const string NTH_NEXT_METHOD_NAME = 'nthNext';

    private const string STATE_START = 'start';

    private const string STATE_REST = 'rest';

    private const string STATE_DONE = 'done';

    private string $currentState = self::STATE_START;

    /** @psalm-suppress PropertyNotSetInConstructor */
    private Symbol $currentListSymbol;

    /** @psalm-suppress PropertyNotSetInConstructor */
    private Symbol $valueSymbol;

    /** True when the value is a vector; bound on the first position. */
    private ?Symbol $indexedSymbol = null;

    /** Positional elements bound so far, the index of the next one. */
    private int $index = 0;

    public function __construct(
        private readonly Deconstructor $deconstructor,
    ) {}

    /**
     * @param list<BindingTuple> $bindings
     * @param mixed              $binding
     * @param mixed              $value
     *
     * @throws AbstractLocatedException
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        $arrSymbol = Symbol::gen()->copyLocationFrom($binding);
        $bindings[] = [$arrSymbol, $value];
        $this->currentListSymbol = $arrSymbol;
        $this->valueSymbol = $arrSymbol;

        /** @var PersistentVectorInterface<mixed> $binding */
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
     * @param list<BindingTuple> $bindings
     */
    private function stateStart(array &$bindings, mixed $current): void
    {
        if ($this->isRest($current)) {
            $this->currentState = self::STATE_REST;
            return;
        }

        $this->bindIndexedOnce($bindings, $current);

        $accessSymbol = $this->synthetic(Symbol::gen()->copyLocationFrom($current));
        $accessValue = $this->createIndexedGuard(
            $current,
            $this->createIndexedRead($current),
            $this->createBindingValue(self::FIRST_SYMBOL_NAME, $current),
        );
        $bindings[] = [$accessSymbol, $accessValue];

        $nextSymbol = $this->synthetic(Symbol::gen()->copyLocationFrom($current));
        $nextValue = $this->createIndexedGuard(
            $current,
            null,
            $this->createBindingValue(self::NEXT_SYMBOL_NAME, $current),
        );
        $bindings[] = [$nextSymbol, $nextValue];
        $this->currentListSymbol = $nextSymbol;
        ++$this->index;

        $this->deconstructor->deconstructBindings($bindings, $current, $accessSymbol);
    }

    /**
     * @param list<BindingTuple> $bindings
     */
    private function stateRest(array &$bindings, mixed $current): void
    {
        $this->currentState = self::STATE_DONE;
        $accessSymbol = Symbol::gen()->copyLocationFrom($current);
        if ($this->index === 0) {
            $bindings[] = [$accessSymbol, $this->currentListSymbol];
        } else {
            $accessSymbol = $this->synthetic($accessSymbol);
            $bindings[] = [
                $accessSymbol,
                $this->createIndexedGuard($current, $this->createIndexedRest($current), $this->currentListSymbol),
            ];
        }

        $this->deconstructor->deconstructBindings($bindings, $current, $accessSymbol);
    }

    private function isRest(mixed $current): bool
    {
        return $current instanceof Symbol
            && $current->getName() === self::REST_SYMBOL_NAME;
    }

    /**
     * `iv (php/instanceof v PersistentVectorInterface)`, once per pattern and
     * only when it has a position, so `[]` and `[& r]` expand as before.
     *
     * The class symbol carries no location on purpose: it is the compiler's
     * spelling, and a located `\` symbol would announce the separator
     * deprecation against the user's pattern.
     *
     * @param list<BindingTuple> $bindings
     */
    private function bindIndexedOnce(array &$bindings, mixed $current): void
    {
        if ($this->indexedSymbol instanceof Symbol) {
            return;
        }

        $this->indexedSymbol = $this->synthetic(Symbol::gen()->copyLocationFrom($current));
        $bindings[] = [
            $this->indexedSymbol,
            Phel::list([
                Symbol::create(self::INSTANCEOF_SYMBOL_NAME)->copyLocationFrom($current),
                $this->valueSymbol,
                Symbol::create(self::INDEXED_CLASS_NAME),
            ])->copyLocationFrom($current),
        ];
    }

    /**
     * Marks a binding the expansion adds as compiler plumbing, see
     * {@see ReturnTypeInferrer::SYNTHETIC_BINDING}.
     */
    private function synthetic(Symbol $symbol): Symbol
    {
        return $symbol->withMeta(Phel::map(Keyword::create(ReturnTypeInferrer::SYNTHETIC_BINDING), true));
    }

    /**
     * `(if (php/=== iv true) indexed walk)`.
     *
     * The comparison is what lets the emitter test `iv` as a PHP bool rather
     * than through Phel's truthiness adapter, which costs more than the
     * whole test on the walk path.
     *
     * @return PersistentListInterface<mixed>
     */
    private function createIndexedGuard(mixed $current, mixed $indexed, mixed $walk): PersistentListInterface
    {
        return Phel::list([
            Symbol::create(Symbol::NAME_IF)->copyLocationFrom($current),
            Phel::list([
                Symbol::create(self::IDENTICAL_SYMBOL_NAME)->copyLocationFrom($current),
                $this->indexedSymbol,
                true,
            ])->copyLocationFrom($current),
            $indexed,
            $walk,
        ])->copyLocationFrom($current);
    }

    /**
     * `(php/aget v i)`: `nil` past the end, like `first` on an exhausted walk.
     *
     * @return PersistentListInterface<mixed>
     */
    private function createIndexedRead(mixed $current): PersistentListInterface
    {
        return Phel::list([
            Symbol::create(Symbol::NAME_PHP_ARRAY_GET)->copyLocationFrom($current),
            $this->valueSymbol,
            $this->index,
        ])->copyLocationFrom($current);
    }

    /**
     * `(.cdr v)` after one position, the `[x & xs]` of every recursion over a
     * vector, else `(Destructure/nthNext v i)`.
     *
     * @return PersistentListInterface<mixed>
     */
    private function createIndexedRest(mixed $current): PersistentListInterface
    {
        if ($this->index === 1) {
            return Phel::list([
                Symbol::create(Symbol::NAME_PHP_OBJECT_CALL)->copyLocationFrom($current),
                $this->valueSymbol,
                Phel::list([Symbol::create(self::CDR_METHOD_NAME)->copyLocationFrom($current)])->copyLocationFrom($current),
            ])->copyLocationFrom($current);
        }

        // The class symbol carries no location on purpose: it is the
        // compiler's spelling, and a located `\` symbol would announce the
        // separator deprecation against the user's pattern.
        return Phel::list([
            Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL)->copyLocationFrom($current),
            Symbol::create(self::DESTRUCTURE_CLASS_NAME),
            Phel::list([
                Symbol::create(self::NTH_NEXT_METHOD_NAME)->copyLocationFrom($current),
                $this->valueSymbol,
                $this->index,
            ])->copyLocationFrom($current),
        ])->copyLocationFrom($current);
    }

    /**
     * @return PersistentListInterface<mixed>
     */
    private function createBindingValue(string $symbolName, mixed $current): PersistentListInterface
    {
        return Phel::list([
            (Symbol::create($symbolName))->copyLocationFrom($current),
            $this->currentListSymbol,
        ])->copyLocationFrom($current);
    }

    /**
     * @param PersistentVectorInterface<mixed> $binding
     *
     * @throws AnalyzerException
     */
    private function triggerUnsupportedBindingFormException(PersistentVectorInterface $binding): never
    {
        throw AnalyzerException::withLocation(
            sprintf(
                'Unsupported binding form, only one symbol can follow the %s parameter',
                self::REST_SYMBOL_NAME,
            ),
            $binding,
        );
    }
}
