<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer;

use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;

use function array_map;
use function array_unique;
use function array_values;
use function implode;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;

/**
 * Builds the error for a symbol nothing resolves, looking where the symbol
 * says it lives: a qualified symbol is searched in its own namespace, never
 * among the current namespace's names, and a bare one that a bundled
 * namespace defines names the `:require` that brings it in.
 *
 * @internal
 */
final readonly class UnresolvedSymbolAdvisor
{
    private const int MIN_PREFIX_LENGTH = 3;

    public function __construct(
        private AnalyzerInterface $analyzer,
        private BundledSymbolIndex $bundledSymbols,
        private SymbolSuggestionProvider $suggestionProvider,
    ) {}

    public function exceptionFor(Symbol $symbol): AnalyzerException
    {
        $alias = $symbol->getNamespace();

        if ($alias === null) {
            return $this->forBareSymbol($symbol);
        }

        return $this->forQualifiedSymbol($symbol, $alias);
    }

    private function forBareSymbol(Symbol $symbol): AnalyzerException
    {
        $name = $symbol->getName();
        $namespaces = $this->bundledSymbols->namespacesDefining($name);

        if ($namespaces !== []) {
            $advice = sprintf(
                '%s is in %s: add (:require %s :refer [%s])',
                $name,
                implode(' and ', $namespaces),
                $namespaces[0],
                $name,
            );

            return AnalyzerException::cannotResolveSymbol($symbol->getFullName(), $symbol, advice: $advice);
        }

        $suggestions = $this->suggestionProvider->findSimilar($name, $this->analyzer->getAvailableSymbols());

        return AnalyzerException::cannotResolveSymbol($symbol->getFullName(), $symbol, $suggestions);
    }

    private function forQualifiedSymbol(Symbol $symbol, string $alias): AnalyzerException
    {
        // A PHP class (`\Foo/bar`, `DateTime/foo`) is not a namespace to require.
        if (preg_match('/^\\\\|^[A-Z]/', $alias) === 1) {
            return AnalyzerException::cannotResolveSymbol($symbol->getFullName(), $symbol);
        }

        $canonicalAlias = str_replace('\\', '.', $alias);
        $namespace = $this->analyzer->resolveRequireAlias($canonicalAlias) ?? $canonicalAlias;
        $names = array_values(array_unique([
            ...$this->analyzer->getPublicDefinitionNames($namespace),
            ...$this->bundledSymbols->namesIn($namespace),
        ]));

        if ($names !== []) {
            $suggestions = array_map(
                static fn(string $name): string => $alias . '/' . $name,
                $this->suggestionProvider->findSimilar($symbol->getName(), $names),
            );

            return AnalyzerException::cannotResolveSymbol($symbol->getFullName(), $symbol, $suggestions);
        }

        return AnalyzerException::cannotResolveSymbol(
            $symbol->getFullName(),
            $symbol,
            advice: $this->unknownNamespaceAdvice($canonicalAlias),
        );
    }

    private function unknownNamespaceAdvice(string $alias): string
    {
        if (str_contains($alias, '.')) {
            $similar = $this->suggestionProvider->findSimilar($alias, $this->bundledSymbols->namespaces());

            return $similar === []
                ? sprintf("No namespace '%s'", $alias)
                : sprintf("No namespace '%s'. Did you mean '%s'?", $alias, $similar[0]);
        }

        $namespace = $this->bundledNamespaceNamedLike($alias);

        return $namespace === null
            ? sprintf("No namespace or alias '%s'", $alias)
            : sprintf("No namespace or alias '%s'. Did you mean (:require %s :as %s)?", $alias, $namespace, $alias);
    }

    /**
     * Matches the alias against the last segment of each bundled namespace:
     * `json` names `phel.json`, and `str` is the start of `phel.string`. A
     * prefix shorter than three letters starts too many names to mean one.
     */
    private function bundledNamespaceNamedLike(string $alias): ?string
    {
        $prefixMatch = null;

        foreach ($this->bundledSymbols->namespaces() as $namespace) {
            $lastSegment = substr($namespace, (int) strrpos($namespace, '.') + 1);

            if ($lastSegment === $alias) {
                return $namespace;
            }

            if ($prefixMatch === null && strlen($alias) >= self::MIN_PREFIX_LENGTH && str_starts_with($lastSegment, $alias)) {
                $prefixMatch = $namespace;
            }
        }

        return $prefixMatch;
    }
}
