<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel;
use Phel\Api\Domain\PhelFnNormalizerInterface;
use Phel\Api\Domain\SymbolMetadataFinderInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Shared\Api\PhelFunction;
use Phel\Shared\MungeInterface;
use Phel\Shared\ScalarCoercion;

use function preg_split;
use function strrpos;
use function substr;
use function trim;

/**
 * Resolves a Phel symbol to its `PhelFunction` snapshot.
 *
 * Probes the runtime registry first so session-defined definitions (`defn`
 * in the REPL/nREPL) and library functions loaded at runtime resolve, then
 * falls back to the static documented-symbol catalog for native special
 * forms (`def`, `fn`, `if`, ...) which never live in the registry.
 */
final class SymbolMetadataFinder implements SymbolMetadataFinderInterface
{
    private const string CORE_NAMESPACE = 'phel.core';

    /** @var list<PhelFunction>|null */
    private ?array $catalogCache = null;

    public function __construct(
        private readonly MungeInterface $munge,
        private readonly PhelFnNormalizerInterface $catalog,
    ) {}

    public function find(string $symbol, string $currentNs = 'user'): ?PhelFunction
    {
        if ($symbol === '') {
            return null;
        }

        return $this->lookupInRegistry($symbol, $currentNs)
            ?? $this->lookupInCatalog($symbol);
    }

    private function lookupInRegistry(string $symbol, string $currentNs): ?PhelFunction
    {
        [$ns, $name] = $this->splitSymbol($symbol);

        if ($ns !== null) {
            return $this->lookupQualified($ns, $name);
        }

        foreach ($this->candidateNamespacesFor($currentNs) as $candidate) {
            $found = $this->lookupQualified($candidate, $name);
            if ($found instanceof PhelFunction) {
                return $found;
            }
        }

        return $this->scanAllNamespaces($name);
    }

    private function lookupInCatalog(string $symbol): ?PhelFunction
    {
        $this->catalogCache ??= $this->catalog->getPhelFunctions();

        foreach ($this->catalogCache as $fn) {
            if ($fn->nameWithNamespace() === $symbol) {
                return $fn;
            }

            if ($fn->name === $symbol && ($fn->namespace === 'core' || $fn->namespace === '')) {
                return $fn;
            }
        }

        return null;
    }

    /**
     * Canonical (dot) namespace form. Mirrors `Munge::canonicalNs()` without
     * importing the concrete class — `encodeRegistryKey()` already
     * canonicalises internally; this is only used to populate the human-
     * readable namespace on the returned `PhelFunction`.
     */
    private function canonicalNs(string $ns): string
    {
        return str_replace('\\', '.', $ns);
    }

    /**
     * @return array{0:?string,1:string}
     */
    private function splitSymbol(string $symbol): array
    {
        $pos = strrpos($symbol, '/');
        if ($pos === false) {
            return [null, $symbol];
        }

        return [substr($symbol, 0, $pos), substr($symbol, $pos + 1)];
    }

    /**
     * @return list<string>
     */
    private function candidateNamespacesFor(string $currentNs): array
    {
        $canonical = $this->canonicalNs($currentNs);
        if ($canonical === self::CORE_NAMESPACE) {
            return [self::CORE_NAMESPACE];
        }

        return [$canonical, self::CORE_NAMESPACE];
    }

    private function lookupQualified(string $ns, string $name): ?PhelFunction
    {
        $canonical = $this->canonicalNs($ns);
        $encodedNs = $this->munge->encodeRegistryKey($canonical);
        $encodedName = $this->munge->encode($name);

        $meta = Phel::getDefinitionMetaData($encodedNs, $encodedName);
        if (!$meta instanceof PersistentMapInterface) {
            return null;
        }

        return $this->toPhelFunction($canonical, $name, $meta);
    }

    private function scanAllNamespaces(string $name): ?PhelFunction
    {
        $encodedName = $this->munge->encode($name);

        foreach (Phel::getNamespaces() as $registryNs) {
            $meta = Phel::getDefinitionMetaData($registryNs, $encodedName);
            if ($meta instanceof PersistentMapInterface) {
                return $this->toPhelFunction(
                    $this->munge->decodeNs($registryNs),
                    $name,
                    $meta,
                );
            }
        }

        return null;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $meta
     */
    private function toPhelFunction(string $namespace, string $name, PersistentMapInterface $meta): PhelFunction
    {
        $doc = ScalarCoercion::toString($meta[Keyword::create('doc')] ?? null);
        $signatures = $this->extractSignatures($meta, $doc, $name);

        $file = '';
        $line = 0;
        $location = $meta[Keyword::create('start-location')] ?? null;
        if ($location instanceof PersistentMapInterface) {
            $file = ScalarCoercion::toString($location[Keyword::create('file')] ?? null);
            $line = ScalarCoercion::toInt($location[Keyword::create('line')] ?? null);
        }

        return new PhelFunction(
            namespace: $namespace,
            name: $name,
            doc: $doc,
            signatures: $signatures,
            description: '',
            file: $file,
            line: $line,
        );
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $meta
     *
     * @return list<string>
     */
    private function extractSignatures(PersistentMapInterface $meta, string $doc, string $name): array
    {
        // `DefSymbol` currently writes the arglist under the plain string key
        // `"arglists"`. Probe the `:arglists` keyword form too so a future
        // switch to keyword keys (matching the rest of def-meta) keeps the
        // runtime signature path live instead of silently degrading to the
        // docstring fallback.
        $arglists = $meta['arglists'] ?? $meta[Keyword::create('arglists')] ?? null;
        if ($arglists !== null && $arglists !== '') {
            return $this->splitArglists(ScalarCoercion::toString($arglists), $name);
        }

        return DocstringSignatureParser::parse($doc)['signatures'];
    }

    /**
     * @return list<string>
     */
    private function splitArglists(string $arglists, string $name): array
    {
        $lines = preg_split('/\R/', trim($arglists)) ?: [];

        $signatures = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $signatures[] = $this->normalizeSignature($trimmed, $name);
        }

        return $signatures;
    }

    /**
     * The runtime stores params-only arglists (e.g. `[n]` or `[& xs]`).
     * Wrap them in `(name args)` form so signatures match the convention
     * used by docstring-parsed signatures like `(map f & colls)`.
     */
    private function normalizeSignature(string $signature, string $name): string
    {
        if ($signature === '' || $signature[0] === '(') {
            return $signature;
        }

        if ($signature[0] !== '[') {
            return $signature;
        }

        $inner = trim(substr($signature, 1, -1));
        return $inner === '' ? '(' . $name . ')' : '(' . $name . ' ' . $inner . ')';
    }
}
