<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;

use Phel;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\DeconstructorInterface;
use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Shared\Printer\Printer;

use function array_key_exists;
use function sprintf;

/**
 * @phpstan-import-type BindingTuple from DeconstructorInterface
 *
 * @internal
 */
final class MapBindingDeconstructor implements BindingDeconstructorInterface
{
    private const array LOOKUP_DIRECTIVES = ['keys' => true, 'strs' => true, 'syms' => true];

    /** @psalm-suppress PropertyNotSetInConstructor */
    private Symbol $mapSymbol;

    /** @var array<string, bool|float|int|string|TypeInterface|null> */
    private array $orDefaults = [];

    public function __construct(
        private readonly Deconstructor $deconstructor,
    ) {}

    /**
     * @param list<BindingTuple>                       $bindings
     * @param PersistentMapInterface<mixed, mixed>     $binding  The binding form
     * @param bool|float|int|string|TypeInterface|null $value    The value form
     */
    public function deconstruct(array &$bindings, $binding, $value): void
    {
        $directives = [];
        $asSymbol = null;
        $orMap = null;
        $normalBindings = [];

        foreach ($binding as $key => $bindTo) {
            if ($key instanceof Keyword && isset(self::LOOKUP_DIRECTIVES[$key->getName()])) {
                $directives[] = [$key, $this->assertVectorOfSymbols($binding, $bindTo, (string) $key)];
                continue;
            }

            if ($key instanceof Keyword && $key->getName() === 'as') {
                $asSymbol = $bindTo;
                continue;
            }

            if ($key instanceof Keyword && $key->getName() === 'or') {
                $orMap = $bindTo;
                continue;
            }

            $normalBindings[] = $this->resolveNormalPair($binding, $key, $bindTo);
        }

        $this->orDefaults = [];
        if ($orMap instanceof PersistentMapInterface) {
            foreach ($orMap as $sym => $default) {
                if ($sym instanceof Symbol) {
                    /** @var bool|float|int|string|TypeInterface|null $default */
                    $this->orDefaults[$sym->getName()] = $default;
                }
            }
        }

        $this->mapSymbol = $asSymbol instanceof Symbol
            ? $asSymbol
            : Symbol::gen()->copyLocationFrom($binding);

        $bindings[] = [$this->mapSymbol, $value];

        foreach ($directives as [$directive, $entries]) {
            foreach ($entries as $entry) {
                if ($entry instanceof Symbol || ($entry instanceof Keyword && $directive->getName() !== 'strs')) {
                    $lookupKey = $this->directiveLookupKey($binding, $directive, $entry);
                    $this->bindingIteration($bindings, $binding, $lookupKey, $this->localFor($entry));
                }
            }
        }

        foreach ($normalBindings as [$key, $bindTo]) {
            /** @var bool|float|int|string|TypeInterface|null $key */
            /** @var bool|float|int|string|TypeInterface|null $bindTo */
            $this->bindingIteration($bindings, $binding, $key, $bindTo);
        }
    }

    /**
     * @param list<BindingTuple>                   $bindings
     * @param PersistentMapInterface<mixed, mixed> $binding
     */
    private function bindingIteration(
        array &$bindings,
        PersistentMapInterface $binding,
        TypeInterface|string|float|int|bool|null $key,
        TypeInterface|string|float|int|bool|null $bindTo,
    ): void {
        $accessSymbol = Symbol::gen()->copyLocationFrom($binding);
        $default = $this->findDefault($bindTo);
        $accessValue = $default !== null
            ? $this->createAccessValueWithDefault($binding, $key, $default[0])
            : $this->createAccessValue($binding, $key);
        $bindings[] = [$accessSymbol, $accessValue];

        $this->deconstructor->deconstructBindings($bindings, $bindTo, $accessSymbol);
    }

    /**
     * Returns [default] if the binding symbol has an :or default, null otherwise.
     * Wrapped in an array to distinguish "no default" from "default is null".
     *
     * @return array{bool|float|int|string|TypeInterface|null}|null
     */
    private function findDefault(
        TypeInterface|string|float|int|bool|null $bindTo,
    ): ?array {
        if (!$bindTo instanceof Symbol) {
            return null;
        }

        $name = $bindTo->getName();

        if (!array_key_exists($name, $this->orDefaults)) {
            return null;
        }

        return [$this->orDefaults[$name]];
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $binding
     *
     * @return PersistentListInterface<mixed>
     */
    private function createAccessValue(
        PersistentMapInterface $binding,
        float|bool|int|string|TypeInterface|null $key,
    ): PersistentListInterface {
        return Phel::list([
            (Symbol::create(Symbol::NAME_PHP_ARRAY_GET))->copyLocationFrom($binding),
            $this->mapSymbol,
            $key,
        ])->copyLocationFrom($binding);
    }

    /**
     * Generates: (if (contains? mapSym key) (php/aget mapSym key) default)
     *
     * @param PersistentMapInterface<mixed, mixed> $binding
     *
     * @return PersistentListInterface<mixed>
     */
    private function createAccessValueWithDefault(
        PersistentMapInterface $binding,
        float|bool|int|string|TypeInterface|null $key,
        float|bool|int|string|TypeInterface|null $default,
    ): PersistentListInterface {
        $containsCheck = Phel::list([
            Symbol::create('contains?')->copyLocationFrom($binding),
            $this->mapSymbol,
            $key,
        ])->copyLocationFrom($binding);

        return Phel::list([
            Symbol::create(Symbol::NAME_IF)->copyLocationFrom($binding),
            $containsCheck,
            $this->createAccessValue($binding, $key),
            $default,
        ])->copyLocationFrom($binding);
    }

    /**
     * Resolves one non-directive pair to `[lookupKey, bindingForm]`.
     *
     * Phel has always written a pair key-first (`{:keyword local}`); Clojure
     * writes it binding-first (`{local :keyword}`). Both are read here, decided
     * per pair by which side can actually be bound, since only a symbol, a
     * vector or a map is a binding form (#3115):
     *
     *   {:kw local}   key not bindable, value is    -> key-first (deprecated)
     *   {local :kw}   key bindable, value not       -> binding-first
     *   {a b}         both bindable                 -> key-first, ambiguous
     *   {:a :b}       neither bindable              -> rejected
     *
     * The ambiguous case keeps its current meaning: a symbol key is evaluated,
     * so `{k v}` looks up whatever `k` holds, and there is no way to tell that
     * from a Clojure-order pair binding `k`. Flipping it silently would change
     * what working code does, so it warns and stays as it is until the next
     * major.
     *
     * @param PersistentMapInterface<mixed, mixed> $binding
     *
     * @return array{mixed, mixed}
     */
    private function resolveNormalPair(
        PersistentMapInterface $binding,
        mixed $key,
        mixed $bindTo,
    ): array {
        $keyBinds = $this->isBindingForm($key);
        $valueBinds = $this->isBindingForm($bindTo);

        if ($keyBinds && !$valueBinds) {
            return [$bindTo, $key];
        }

        if (!$keyBinds && !$valueBinds) {
            $this->rejectUnbindablePair($binding, $key, $bindTo);
        }

        $this->warnKeyFirstPair($binding, $key, $bindTo, ambiguous: $keyBinds);

        return [$key, $bindTo];
    }

    /**
     * Whether a form can be bound to. `:keys`-style directives aside, this is
     * what tells the two pair orders apart, so it has to agree with what
     * {@see Deconstructor::deconstructBindings()} accepts.
     */
    private function isBindingForm(mixed $form): bool
    {
        return $form instanceof Symbol
            || $form instanceof PersistentVectorInterface
            || $form instanceof PersistentMapInterface;
    }

    /**
     * Neither side binds anything, so the pair cannot be either order. This is
     * where a mistyped pattern lands (`{:a :b}`, `{"x" "y"}`), and the message
     * shows both spellings rather than the opaque "Cannot destructure
     * Phel\\Lang\\Keyword" the deconstructor would raise further down.
     *
     * @param PersistentMapInterface<mixed, mixed> $binding
     */
    private function rejectUnbindablePair(
        PersistentMapInterface $binding,
        mixed $key,
        mixed $bindTo,
    ): never {
        $message = sprintf(
            "Cannot destructure: neither side of the pair %s binds anything.\n\n"
            . "A map destructuring pair binds a symbol, a vector or a map to a lookup key,\n"
            . "written either way round:\n"
            . "  {local :key}   ; Clojure order\n"
            . '  {:key local}   ; Phel order, deprecated',
            Printer::readable()->print(Phel::map($key, $bindTo)),
        );

        throw AnalyzerException::withLocation($message, $binding);
    }

    /**
     * Reports a pair still written key-first, pointing at the Clojure-order
     * spelling that replaces it.
     *
     * Gated like every other deprecation: silent unless `--warn-deprecations`
     * is on, deduped per `(file, pair)`, and attributed to the macro that wrote
     * the pattern rather than to the call site.
     *
     * @param PersistentMapInterface<mixed, mixed> $binding
     */
    private function warnKeyFirstPair(
        PersistentMapInterface $binding,
        mixed $key,
        mixed $bindTo,
        bool $ambiguous,
    ): void {
        $location = $binding->getStartLocation();
        if (!$location instanceof SourceLocation) {
            return;
        }

        $written = Printer::readable()->print(Phel::map($key, $bindTo));
        $flipped = Printer::readable()->print(Phel::map($bindTo, $key));

        DeprecationWarnings::warnOnceAtOrigin(
            $location,
            'map-destructure-key-first|' . $written,
            fn(string $file, int $line): string => $ambiguous
                ? $this->ambiguousPairMessage($written, $file, $line)
                : $this->keyFirstPairMessage($written, $flipped, $file, $line),
        );
    }

    /**
     * The pair has a replacement spelling, so the notice names it.
     */
    private function keyFirstPairMessage(string $written, string $flipped, string $file, int $line): string
    {
        return sprintf(
            'Key-first map destructuring pair %s at %s:%d is deprecated; write it binding-first, '
            . 'as %s. The key-first order will be removed in a future release.',
            $written,
            $file,
            $line,
            $flipped,
        );
    }

    /**
     * Both sides bind, so there is no mechanical replacement to suggest: the
     * flip would change which side is the lookup key. The notice says what the
     * pair means today and what to write instead.
     */
    private function ambiguousPairMessage(string $written, string $file, int $line): string
    {
        return sprintf(
            'Ambiguous map destructuring pair %s at %s:%d: both sides are binding forms, so it is '
            . 'read key-first and the lookup key is what the left side evaluates to. Binding-first '
            . 'is how every other pair is read, and this shape will follow at the next major; write '
            . 'the lookup key as a keyword or a string to keep it unambiguous.',
            $written,
            $file,
            $line,
        );
    }

    /**
     * The key one `:keys`, `:syms` or `:strs` entry reads, built as Clojure
     * does: the directive's namespace wins over the entry's, so
     * `{:my/keys [a]}` and `{:keys [my/a]}` both read `:my/a`. `::keys`
     * arrives here already qualified by the reader. `:strs` reads the entry
     * as written and ignores the directive's namespace.
     *
     * @param PersistentMapInterface<mixed, mixed> $binding
     *
     * @return Keyword|PersistentListInterface<mixed>|string
     */
    private function directiveLookupKey(
        PersistentMapInterface $binding,
        Keyword $directive,
        Keyword|Symbol $entry,
    ): Keyword|PersistentListInterface|string {
        $namespace = $directive->getNamespace() ?? $entry->getNamespace();

        return match ($directive->getName()) {
            'keys' => Keyword::create($entry->getName(), $namespace),
            'strs' => $entry->getFullName(),
            default => Phel::list([
                Symbol::create(Symbol::NAME_QUOTE)->copyLocationFrom($binding),
                Symbol::createForNamespace($namespace, $entry->getName())->copyLocationFrom($binding),
            ])->copyLocationFrom($binding),
        };
    }

    /**
     * A directive entry binds a bare local named after its name part, so
     * `my/a` and `:my/a` both bind `a`.
     */
    private function localFor(Keyword|Symbol $entry): Symbol
    {
        if ($entry instanceof Symbol && $entry->getNamespace() === null) {
            return $entry;
        }

        return Symbol::createForNamespace(null, $entry->getName())->copyLocationFrom($entry);
    }

    /**
     * `:keys`, `:strs`, `:syms` (namespaced or not) each take a vector of
     * symbols; `:keys` and `:syms` also take keywords. Anything else is
     * rejected here with a one-line shape error rather than being silently
     * dropped further down the deconstructor.
     *
     * @param PersistentMapInterface<mixed, mixed> $binding
     *
     * @return PersistentVectorInterface<mixed>
     */
    private function assertVectorOfSymbols(
        PersistentMapInterface $binding,
        mixed $bindTo,
        string $directive,
    ): PersistentVectorInterface {
        if (!$bindTo instanceof PersistentVectorInterface) {
            throw AnalyzerException::withLocation(
                sprintf('`{%s [...]}` expects a vector of symbols', $directive),
                $binding,
            );
        }

        return $bindTo;
    }
}
