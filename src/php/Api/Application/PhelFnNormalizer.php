<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\PhelFnGroupKeyGeneratorInterface;
use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Api\Domain\PhelFnNormalizerInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Shared\Api\PhelFunction;
use Phel\Shared\ScalarCoercion;

final readonly class PhelFnNormalizer implements PhelFnNormalizerInterface
{
    private const string GITHUB_BASE_URL = 'https://github.com/phel-lang/phel-lang/blob/';

    private const string DEFAULT_NAMESPACE = 'core';

    /**
     * @param list<string> $allNamespaces
     */
    public function __construct(
        private PhelFnLoaderInterface $phelFnLoader,
        private PhelFnGroupKeyGeneratorInterface $phelFnGroupKeyGenerator,
        private array $allNamespaces = [],
        private string $githubRef = 'main',
    ) {}

    /**
     * Normalizes raw Phel function metadata into a sorted, de-duplicated list.
     *
     * Pipeline:
     * 1. Load metadata for the requested namespaces (defaults to all known).
     * 2. Skip entries flagged `:private`.
     * 3. Parse each docstring into signatures + description.
     * 4. Group by generated group key, build {@see PhelFunction} DTOs, sort each group.
     * 5. Prepend native symbols (special forms / built-ins) so they override
     *    any homonymous runtime def, then drop duplicate `namespace/name` pairs.
     *
     * @param list<string> $namespaces
     *
     * @return list<PhelFunction>
     */
    public function getPhelFunctions(array $namespaces = []): array
    {
        if ($namespaces === []) {
            $namespaces = $this->allNamespaces;
        }

        $normalizedData = $this->phelFnLoader->getNormalizedPhelFunctions($namespaces);

        $normalizedFns = [];
        foreach ($normalizedData as $fnName => $meta) {
            $isPrivate = $meta[Keyword::create('private')] ?? false;
            if ($isPrivate) {
                continue;
            }

            $doc = ScalarCoercion::toString($meta[Keyword::create('doc')] ?? null);
            $parsed = DocstringSignatureParser::parse($doc);
            $signatures = $parsed['signatures'];
            $description = $parsed['description'];

            $namespace = $this->extractNamespace($fnName);
            $groupKey = $this->phelFnGroupKeyGenerator->generateGroupKey($namespace, $fnName);

            $file = '';
            $line = 0;
            $location = $meta[Keyword::create('start-location')] ?? null;
            if ($location instanceof PersistentMapInterface) {
                $file = ScalarCoercion::toString($location[Keyword::create('file')] ?? null);
                $file = $this->toRelativeFile($file);
                $line = ScalarCoercion::toInt($location[Keyword::create('line')] ?? null);
            }

            $normalizedFns[$groupKey][$fnName] = new PhelFunction(
                namespace: $namespace,
                name: $this->extractNameWithoutNamespace($fnName),
                doc: $doc,
                signatures: $signatures,
                description: $description,
                groupKey: $groupKey,
                githubUrl: $this->toGithubUrl($file, $line),
                docUrl: ScalarCoercion::toString($meta[Keyword::create('docUrl')] ?? null),
                file: $file,
                line: $line,
                meta: $this->metaToArray($meta),
            );
        }

        foreach ($normalizedFns as &$values) {
            usort($values, $this->sortingPhelFunctionsCallback());
        }

        unset($values);

        $flattenedFns = array_merge(...array_values($normalizedFns));

        $result = [
            ...$this->normalizeNativeSymbols($flattenedFns),
            ...$flattenedFns,
        ];

        usort($result, $this->sortingPhelFunctionsCallback());

        return $this->removeDuplicates($result);
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $meta
     *
     * @return array<string, mixed>
     */
    private function metaToArray(PersistentMapInterface $meta): array
    {
        $result = [];
        foreach ($meta as $key => $value) {
            $name = $key instanceof Keyword ? $key->getName() : ScalarCoercion::toString($key);
            $result[$name] = $value;
        }

        return $result;
    }

    private function extractNamespace(string $fnName): string
    {
        if ($fnName === '/') {
            return self::DEFAULT_NAMESPACE;
        }

        $pos = strrpos($fnName, '/');

        return $pos === false ? self::DEFAULT_NAMESPACE : substr($fnName, 0, $pos);
    }

    private function extractNameWithoutNamespace(string $fnName): string
    {
        if ($fnName === '/') {
            return $fnName;
        }

        $pos = strrpos($fnName, '/');

        return $pos === false ? $fnName : substr($fnName, $pos + 1);
    }

    private function sortingPhelFunctionsCallback(): callable
    {
        return static fn(PhelFunction $a, PhelFunction $b): int => (($a->namespace <=> $b->namespace) !== 0)
            ? $a->namespace <=> $b->namespace
            : ($a->name <=> $b->name);
    }

    /**
     * @param array<array-key, PhelFunction> $originalNormalizedFns
     *
     * @return list<PhelFunction>
     */
    private function normalizeNativeSymbols(array $originalNormalizedFns): array
    {
        $result = [];
        foreach ($this->phelFnLoader->getNormalizedNativeSymbols() as $name => $custom) {
            // NativeSymbolCatalog entries don't populate file/line; defaults apply.
            $file = $this->toRelativeFile($custom['file'] ?? '');
            $line = $custom['line'] ?? 0;

            $original = $originalNormalizedFns[$name] ?? null;
            $namespace = $this->extractNamespace($name);

            $customSignature = $custom['signatures'] ?? [];
            $signatures = $customSignature !== [] ? $customSignature : ($original->signatures ?? []);

            $result[] = new PhelFunction(
                namespace: $namespace,
                name: $this->extractNameWithoutNamespace($name),
                doc: $custom['doc'] ?? $original->doc ?? '',
                signatures: $signatures,
                description: $custom['desc'] ?? $original->description ?? '',
                groupKey: $this->phelFnGroupKeyGenerator->generateGroupKey($namespace, $name),
                githubUrl: $this->toGithubUrl($file, $line),
                docUrl: $custom['docUrl'] ?? '',
                file: $file,
                line: $line,
                meta: ['example' => $custom['example'] ?? ''],
            );
        }

        return $result;
    }

    /**
     * @param list<PhelFunction> $fns
     *
     * @return list<PhelFunction>
     */
    private function removeDuplicates(array $fns): array
    {
        $seenNames = [];

        $filtered = array_filter($fns, static function (PhelFunction $fn) use (&$seenNames): bool {
            $fnName = $fn->namespace . '/' . $fn->name;
            if (isset($seenNames[$fnName])) {
                return false;
            }

            $seenNames[$fnName] = true;
            return true;
        });

        return array_values($filtered);
    }

    private function toRelativeFile(string $file): string
    {
        $normalized = str_replace('\\', '/', $file);
        $pos = strpos($normalized, '/src/');
        if ($pos !== false) {
            return substr($normalized, $pos + 1);
        }

        return ltrim($normalized, '/');
    }

    private function toGithubUrl(string $file, int $line): string
    {
        if ($file === '') {
            return '';
        }

        $url = self::GITHUB_BASE_URL . $this->githubRef . '/' . $file;
        if ($line > 0) {
            $url .= '#L' . $line;
        }

        return $url;
    }
}
