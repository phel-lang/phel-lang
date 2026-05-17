<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Lang\BigDecimal;
use Phel\Lang\BigInteger;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LazySeq\LazyCons;
use Phel\Lang\Collections\LazySeq\LazySeqInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\MapEntry;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Delay;
use Phel\Lang\Keyword;
use Phel\Lang\PhelFuture;
use Phel\Lang\PhelVar;
use Phel\Lang\Rational;
use Phel\Lang\Reduced;
use Phel\Lang\Symbol;
use Phel\Lang\Uuid;
use Phel\Lang\Variable;
use Phel\Lang\Volatile;

/**
 * Pre-registers a curated set of `Phel\Lang\*` PHP class/interface aliases
 * in every Phel namespace so authors do not have to repeat 5-segment
 * `(:use Phel.Lang.Collections.LazySeq.LazySeqInterface)` lines for the
 * common interop surface. The `Interface` suffix is dropped at the Phel
 * level, matching Clojure's `(instance? PersistentVector x)` ergonomics.
 *
 * User-supplied `(:use ...)` aliases in the same namespace overwrite
 * defaults: defaults are registered before user uses, so re-registration
 * with the same short name wins last.
 */
final class DefaultLangAliasesRegistrar
{
    /** @var array<string, string> */
    public const array DEFAULT_USE_ALIASES = [
        // Sequence types
        'LazySeq' => LazySeqInterface::class,
        'LazyCons' => LazyCons::class,
        'Cons' => LazyCons::class,
        'PersistentList' => PersistentListInterface::class,
        'PersistentVector' => PersistentVectorInterface::class,
        'PersistentMap' => PersistentMapInterface::class,
        'PersistentHashSet' => PersistentHashSetInterface::class,
        'MapEntry' => MapEntry::class,
        // Names
        'Keyword' => Keyword::class,
        'Symbol' => Symbol::class,
        // Numeric tower (Clojure-aligned names)
        'BigInt' => BigInteger::class,
        'BigDecimal' => BigDecimal::class,
        'Ratio' => Rational::class,
        // Concurrency primitives (Clojure-aligned names)
        'Atom' => Variable::class,
        'Var' => PhelVar::class,
        'Volatile' => Volatile::class,
        'Reduced' => Reduced::class,
        'Delay' => Delay::class,
        'Future' => PhelFuture::class,
        // Misc value types
        'UUID' => Uuid::class,
    ];

    /**
     * Pre-built `(alias, target)` symbol pairs. Built lazily on first
     * register call; `Symbol::create` is not interned, so caching keeps
     * `(ns ...)` / `(in-ns ...)` declarations from allocating 34 fresh
     * Symbols every time.
     *
     * @var list<array{Symbol, Symbol}>|null
     */
    private static ?array $cachedPairs = null;

    public static function register(AnalyzerInterface $analyzer, string $ns): void
    {
        foreach (self::pairs() as [$alias, $target]) {
            $analyzer->addUseAlias($ns, $alias, $target);
        }
    }

    /**
     * @return list<array{Symbol, Symbol}>
     */
    private static function pairs(): array
    {
        if (self::$cachedPairs === null) {
            $pairs = [];
            foreach (self::DEFAULT_USE_ALIASES as $shortName => $fqn) {
                $pairs[] = [Symbol::create($shortName), Symbol::create('\\' . $fqn)];
            }

            self::$cachedPairs = $pairs;
        }

        return self::$cachedPairs;
    }
}
