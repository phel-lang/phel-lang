<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\NsNode;
use Phel\Compiler\Domain\Analyzer\Environment\BackslashSeparatorDeprecator;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\PhpClassLike;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\FrameworkNamespaces;
use Phel\Shared\Munge;

use function count;
use function explode;
use function is_string;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_replace;

/**
 * (ns name (:require ...) (:use ...)).
 *
 * Declares a namespace with optional requires and PHP class imports.
 *
 * @internal
 */
final class NsSymbol implements SpecialFormAnalyzerInterface
{
    use AssertsFormArityTrait;
    use WithAnalyzerTrait;

    private const string INVALID_NAMESPACE_MESSAGE = <<<'TXT'
Invalid namespace. A valid namespace name starts with a letter or underscore,
followed by any number of letters, numbers, underscores, or dashes.
Elements are split by a dot.
TXT;

    private const string NAMESPACE_PART_PATTERN = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\-\x7f-\xff]*$/';

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): NsNode
    {
        $this->assertArityAtLeast($list, 2, '(ns name)');

        $nsSymbol = $list->get(1);
        if (!($nsSymbol instanceof Symbol)) {
            throw AnalyzerException::wrongArgumentType("First argument of 'ns", 'Symbol', $nsSymbol, $list);
        }

        BackslashSeparatorDeprecator::getInstance()->maybeWarn($nsSymbol);

        $ns = $this->normalizeNamespaceSeparators($nsSymbol->getName());
        $parts = explode('.', $ns);

        $this->assertValidNamespace($parts, $nsSymbol);

        $this->analyzer->setNamespace($ns);
        DefaultLangAliasesRegistrar::register($this->analyzer, $ns);

        $requireNs = [];
        $requireFiles = [];
        for ($forms = $list->rest()->cdr(); $forms !== null; $forms = $forms->cdr()) {
            $import = $forms->first();

            if (!($import instanceof PersistentListInterface)) {
                throw AnalyzerException::withLocation("Import in 'ns must be Lists.", $list, errorCode: ErrorCode::TYPE_ERROR);
            }

            $value = $import->get(0);

            /** @var PersistentListInterface<mixed> $import */
            if ($this->isKeywordWithName($value, 'use')) {
                $this->analyzeUse($ns, $import);
            } elseif ($this->isKeywordWithName($value, 'require')) {
                $requireNs = [...$requireNs, ...$this->analyzeRequire($ns, $import)];
            } elseif ($this->isKeywordWithName($value, 'require-file')) {
                $requireFiles[] = $this->analyzeRequireFile($import);
            } elseif ($value instanceof Keyword) {
                throw AnalyzerException::withLocation(
                    sprintf("Unexpected keyword %s encountered in 'ns. Expected :use or :require.", $value->getName()),
                    $value,
                );
            }
        }

        ReplReferInjector::injectIfReplMode($this->analyzer, $ns);

        return new NsNode($ns, $requireNs, $requireFiles, $list->getStartLocation());
    }

    private function isKeywordWithName(mixed $x, string $name): bool
    {
        return $x instanceof Keyword && $x->getName() === $name;
    }

    /**
     * @param PersistentListInterface<mixed> $import
     */
    private function analyzeUse(string $ns, PersistentListInterface $import): void
    {
        new UseAliasRegistrar($this->analyzer)->register($ns, $import);
    }

    /**
     * @param PersistentVectorInterface<mixed>|null $refer
     * @param PersistentListInterface<mixed>        $import
     *
     * @return list<Symbol>
     */
    private function extractRefer(?PersistentVectorInterface $refer, PersistentListInterface $import): array
    {
        if (!$refer instanceof PersistentVectorInterface) {
            return [];
        }

        $result = [];
        foreach ($refer as $ref) {
            if (!$ref instanceof Symbol) {
                throw AnalyzerException::wrongArgumentType('Each refer element', 'Symbol', $ref, $import);
            }

            $result[] = $ref;
        }

        return $result;
    }

    private function createAliasFromSymbol(?Symbol $alias, Symbol $symbol): Symbol
    {
        if ($alias instanceof Symbol) {
            return $alias;
        }

        $parts = explode('.', $symbol->getName());

        return Symbol::create(array_last($parts));
    }

    /**
     * @param PersistentListInterface<mixed> $import
     *
     * @return list<Symbol>
     */
    private function analyzeRequire(string $ns, PersistentListInterface $import): array
    {
        $elements = $import->toArray();
        $count = count($elements);
        $result = [];

        for ($i = 1; $i < $count; ++$i) {
            $element = $elements[$i];

            if ($element instanceof PersistentVectorInterface) {
                $result[] = $this->analyzeRequireVectorEntry($ns, $element, $import);

                continue;
            }

            if (!$element instanceof Symbol) {
                throw AnalyzerException::withLocation(
                    'First argument in :require must be a symbol or vector.',
                    $import,
                    errorCode: ErrorCode::TYPE_ERROR,
                );
            }

            $nextIndex = $i;
            $result[] = $this->analyzeRequireFlatEntry($ns, $elements, $nextIndex, $import);
            $i = $nextIndex - 1;
        }

        return $result;
    }

    /**
     * Handles a single legacy flat entry (symbol followed by `:as` / `:refer`
     * options), advancing `$index` past the options this entry consumed.
     *
     * @param array<int, mixed>              $elements
     * @param PersistentListInterface<mixed> $import
     */
    private function analyzeRequireFlatEntry(
        string $ns,
        array $elements,
        int &$index,
        PersistentListInterface $import,
    ): Symbol {
        $count = count($elements);

        /** @var Symbol $requireSymbol */
        $requireSymbol = $elements[$index];
        BackslashSeparatorDeprecator::getInstance()->maybeWarn($requireSymbol);
        $requireSymbol = $this->normalizeSymbolSeparators($requireSymbol);

        ++$index;
        $aliasValue = null;
        $referValue = null;

        while ($index < $count) {
            $option = $elements[$index];

            if ($option instanceof Symbol || $option instanceof PersistentVectorInterface) {
                break;
            }

            if (!$option instanceof Keyword) {
                throw AnalyzerException::withLocation(
                    'Unexpected argument in :require. Expected a keyword.',
                    $import,
                    errorCode: ErrorCode::TYPE_ERROR,
                );
            }

            ++$index;

            if ($option->getName() === 'as') {
                $aliasValue = $this->consumeAsAlias($elements, $index, $import);

                continue;
            }

            if ($option->getName() === 'refer') {
                $referValue = $this->consumeReferVector($elements, $index, $import);

                continue;
            }

            throw AnalyzerException::withLocation(
                sprintf('Unexpected keyword %s encountered in :require. Expected :as or :refer.', $option->getName()),
                $option,
            );
        }

        return $this->registerRequire($ns, $requireSymbol, $aliasValue, $referValue, $import);
    }

    /**
     * Handles a single Clojure-style vector entry `[ns-sym & options]`.
     *
     * @param PersistentVectorInterface<mixed> $vector
     * @param PersistentListInterface<mixed>   $import
     */
    private function analyzeRequireVectorEntry(
        string $ns,
        PersistentVectorInterface $vector,
        PersistentListInterface $import,
    ): Symbol {
        $elements = [];
        foreach ($vector as $item) {
            $elements[] = $item;
        }

        $count = count($elements);
        if ($count === 0) {
            throw AnalyzerException::withLocation(
                'First element of :require vector must be a symbol.',
                $import,
                errorCode: ErrorCode::TYPE_ERROR,
            );
        }

        $requireSymbol = $elements[0];
        if (!$requireSymbol instanceof Symbol) {
            throw AnalyzerException::withLocation(
                'First element of :require vector must be a symbol.',
                $import,
                errorCode: ErrorCode::TYPE_ERROR,
            );
        }

        BackslashSeparatorDeprecator::getInstance()->maybeWarn($requireSymbol);
        $requireSymbol = $this->normalizeSymbolSeparators($requireSymbol);

        $index = 1;
        $aliasValue = null;
        $referValue = null;

        while ($index < $count) {
            $option = $elements[$index];

            if (!$option instanceof Keyword) {
                throw AnalyzerException::withLocation(
                    'Unexpected argument in :require vector. Expected a keyword.',
                    $import,
                    errorCode: ErrorCode::TYPE_ERROR,
                );
            }

            ++$index;

            if ($option->getName() === 'as') {
                $aliasValue = $this->consumeAsAlias($elements, $index, $import);

                continue;
            }

            if ($option->getName() === 'refer') {
                $referValue = $this->consumeReferVector($elements, $index, $import);

                continue;
            }

            throw AnalyzerException::withLocation(
                sprintf('Unexpected keyword %s encountered in :require. Expected :as or :refer.', $option->getName()),
                $option,
            );
        }

        return $this->registerRequire($ns, $requireSymbol, $aliasValue, $referValue, $import);
    }

    /**
     * @param array<int, mixed>              $elements
     * @param PersistentListInterface<mixed> $import
     */
    private function consumeAsAlias(array $elements, int &$index, PersistentListInterface $import): Symbol
    {
        if ($index >= count($elements)) {
            throw AnalyzerException::wrongArgumentType('Alias', 'Symbol', null, $import);
        }

        $aliasCandidate = $elements[$index];
        if (!$aliasCandidate instanceof Symbol) {
            throw AnalyzerException::wrongArgumentType('Alias', 'Symbol', $aliasCandidate, $import);
        }

        ++$index;

        return $aliasCandidate;
    }

    /**
     * @param array<int, mixed>              $elements
     * @param PersistentListInterface<mixed> $import
     *
     * @return PersistentVectorInterface<mixed>
     */
    private function consumeReferVector(
        array $elements,
        int &$index,
        PersistentListInterface $import,
    ): PersistentVectorInterface {
        if ($index >= count($elements)) {
            throw AnalyzerException::withLocation('Refer must be a vector', $import, errorCode: ErrorCode::INVALID_SPECIAL_FORM);
        }

        $referCandidate = $elements[$index];
        if ($referCandidate instanceof Keyword && $referCandidate->getName() === 'all') {
            throw AnalyzerException::withLocation(
                ':refer :all is not supported. List the names to refer, as in :refer [upper-case trim], or use :as.',
                $import,
                errorCode: ErrorCode::INVALID_SPECIAL_FORM,
            );
        }

        if (!$referCandidate instanceof PersistentVectorInterface) {
            throw AnalyzerException::withLocation('Refer must be a vector', $import, errorCode: ErrorCode::INVALID_SPECIAL_FORM);
        }

        ++$index;

        return $referCandidate;
    }

    /**
     * @param PersistentVectorInterface<mixed>|null $referValue
     * @param PersistentListInterface<mixed>        $import
     */
    private function registerRequire(
        string $ns,
        Symbol $requireSymbol,
        ?Symbol $aliasValue,
        ?PersistentVectorInterface $referValue,
        PersistentListInterface $import,
    ): Symbol {
        $resolvedSymbol = $this->remapClojureNamespace($requireSymbol);

        $alias = $this->createAliasFromSymbol($aliasValue, $resolvedSymbol);
        $referSymbols = $this->extractRefer($referValue, $import);

        $this->analyzer->addRequireAlias($ns, $alias, $resolvedSymbol);
        $this->analyzer->addRefers($ns, $referSymbols, $resolvedSymbol);

        if ($resolvedSymbol->getName() !== $requireSymbol->getName()) {
            $this->analyzer->addRequireAlias($ns, $requireSymbol, $resolvedSymbol);
        }

        // After registering, so a tool that reports this and reads on still
        // resolves the names that are defined.
        $this->assertRefersAreDefined($resolvedSymbol->getName(), $referSymbols, $referValue, $import);

        return $resolvedSymbol;
    }

    /**
     * Only a loaded namespace can be checked. A file's dependencies load
     * before it compiles, so this covers `run`, `test` and `build`; a
     * namespace the emitted `ns` form has yet to load is left alone.
     *
     * @param list<Symbol>                          $referSymbols
     * @param PersistentVectorInterface<mixed>|null $referValue
     * @param PersistentListInterface<mixed>        $import
     */
    private function assertRefersAreDefined(
        string $requiredNs,
        array $referSymbols,
        ?PersistentVectorInterface $referValue,
        PersistentListInterface $import,
    ): void {
        $registry = Registry::getInstance();
        $munge = new Munge();
        $mungedNs = $munge->encodeRegistryKey($requiredNs);
        if ($referSymbols === [] || !$registry->hasNamespace($mungedNs)) {
            return;
        }

        foreach ($referSymbols as $refer) {
            $name = $refer->getName();
            if ($this->isPhpClassOf($munge, $requiredNs, $name)) {
                continue;
            }

            if (!$registry->isDefined($mungedNs, $name)) {
                $message = sprintf("'%s' is referred from %s, which does not define it.", $name, $requiredNs);
            } elseif ($this->isPrivate($registry->getDefinitionMetaData($mungedNs, $name))) {
                $message = sprintf("'%s' is referred from %s, which keeps it private.", $name, $requiredNs);
            } else {
                continue;
            }

            throw AnalyzerException::withLocation(
                $message,
                $refer->getStartLocation() instanceof SourceLocation ? $refer : ($referValue ?? $import),
                errorCode: ErrorCode::UNRESOLVED_REFER,
            );
        }
    }

    /**
     * `definterface` and `defstruct` names are PHP classes, not registry
     * definitions.
     */
    private function isPhpClassOf(Munge $munge, string $ns, string $name): bool
    {
        return PhpClassLike::exists('\\' . $munge->encodePhpNs($ns) . '\\' . $munge->encode($name));
    }

    private function isPrivate(mixed $meta): bool
    {
        return $meta instanceof PersistentMapInterface
            && $meta->find(Keyword::create('private')) === true;
    }

    /**
     * @param PersistentListInterface<mixed> $import
     */
    private function analyzeRequireFile(PersistentListInterface $import): string
    {
        $file = $import->get(1);
        if (!is_string($file)) {
            throw AnalyzerException::withLocation('First argument in :require-file must be a string.', $import, errorCode: ErrorCode::TYPE_ERROR);
        }

        return $file;
    }

    /**
     * @param list<string> $parts
     */
    private function assertValidNamespace(array $parts, Symbol $nsSymbol): void
    {
        foreach ($parts as $part) {
            if ($part === '' || !$this->isValidNamespacePart($part)) {
                throw AnalyzerException::withLocation(self::INVALID_NAMESPACE_MESSAGE, $nsSymbol);
            }
        }
    }

    private function isValidNamespacePart(string $part): bool
    {
        return preg_match(self::NAMESPACE_PART_PATTERN, $part) === 1;
    }

    /**
     * Accepts `\` as an alternate namespace separator (legacy Phel form)
     * and rewrites it to the canonical `.` so the rest of the compiler
     * pipeline only ever sees dot-separated names.
     */
    private function normalizeNamespaceSeparators(string $ns): string
    {
        return str_replace('\\', '.', $ns);
    }

    private function normalizeSymbolSeparators(Symbol $symbol): Symbol
    {
        $name = $symbol->getName();
        if (!str_contains($name, '\\')) {
            return $symbol;
        }

        return Symbol::createForNamespace(
            $symbol->getNamespace(),
            $this->normalizeNamespaceSeparators($name),
        )->copyLocationFrom($symbol);
    }

    /**
     * Remaps `clojure.*` namespaces to their `phel.*` target when it is
     * registered (`clojure.test` -> `phel.test`, `clojure.set` -> `phel.core`).
     * User-defined `clojure.*` namespaces with no registered target are left
     * untouched.
     */
    private function remapClojureNamespace(Symbol $symbol): Symbol
    {
        $targetNs = FrameworkNamespaces::clojureTarget($symbol->getName());
        if ($targetNs === null) {
            return $symbol;
        }

        $mungedNs = str_replace('-', '_', $targetNs);

        if (Registry::getInstance()->getDefinitionInNamespace($mungedNs) === []) {
            return $symbol;
        }

        return Symbol::createForNamespace(
            $symbol->getNamespace(),
            $targetNs,
        )->copyLocationFrom($symbol);
    }
}
