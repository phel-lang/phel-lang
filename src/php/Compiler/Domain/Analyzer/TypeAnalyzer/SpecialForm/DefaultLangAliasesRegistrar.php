<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Lang\BigDecimal;
use Phel\Lang\BigInteger;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\HashSet\TransientHashSetInterface;
use Phel\Lang\Collections\LazySeq\LazySeqInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\MapEntry;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Map\TransientMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Collections\Vector\TransientVectorInterface;
use Phel\Lang\Delay;
use Phel\Lang\Keyword;
use Phel\Lang\PhelFuture;
use Phel\Lang\Rational;
use Phel\Lang\Symbol;
use Phel\Lang\Uuid;

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
final readonly class DefaultLangAliasesRegistrar
{
    /** @var array<string, string> */
    public const array DEFAULT_USE_ALIASES = [
        'LazySeq' => LazySeqInterface::class,
        'PersistentList' => PersistentListInterface::class,
        'PersistentVector' => PersistentVectorInterface::class,
        'PersistentMap' => PersistentMapInterface::class,
        'PersistentHashSet' => PersistentHashSetInterface::class,
        'TransientVector' => TransientVectorInterface::class,
        'TransientMap' => TransientMapInterface::class,
        'TransientHashSet' => TransientHashSetInterface::class,
        'MapEntry' => MapEntry::class,
        'Keyword' => Keyword::class,
        'Symbol' => Symbol::class,
        'BigInteger' => BigInteger::class,
        'BigDecimal' => BigDecimal::class,
        'Rational' => Rational::class,
        'PhelFuture' => PhelFuture::class,
        'Delay' => Delay::class,
        'Uuid' => Uuid::class,
    ];

    public function __construct(private AnalyzerInterface $analyzer) {}

    public function register(string $ns): void
    {
        foreach (self::DEFAULT_USE_ALIASES as $shortName => $fqn) {
            $this->analyzer->addUseAlias(
                $ns,
                Symbol::create($shortName),
                Symbol::create('\\' . $fqn),
            );
        }
    }
}
