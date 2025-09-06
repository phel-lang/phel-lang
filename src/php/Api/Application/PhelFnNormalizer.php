<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\PhelFnGroupKeyGeneratorInterface;
use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Api\Domain\PhelFnNormalizerInterface;
use Phel\Api\Transfer\PhelFunction;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;

final readonly class PhelFnNormalizer implements PhelFnNormalizerInterface
{
    private const string GITHUB_BASE_URL = 'https://github.com/phel-lang/phel-lang/blob/main/';

    private const string DEFAULT_NAMESPACE = 'core';

    /**
     * @param list<string> $allNamespaces
     */
    public function __construct(
        private PhelFnLoaderInterface $phelFnLoader,
        private PhelFnGroupKeyGeneratorInterface $phelFnGroupKeyGenerator,
        private array $allNamespaces = [],
    ) {
    }

    /**
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

            $doc = $meta[Keyword::create('doc')] ?? '';
            preg_match('#(```phel\n(?<signature>.*)\n```\n)?(?<desc>.*)#s', $doc, $matches);

            $namespace = $this->extractNamespace($fnName);
            $groupKey = $this->phelFnGroupKeyGenerator->generateGroupKey($namespace, $fnName);

            $file = '';
            $line = 0;
            $location = $meta[Keyword::create('start-location')] ?? null;
            if ($location instanceof PersistentMapInterface) {
                $file = (string) ($location[Keyword::create('file')] ?? '');
                $file = $this->toRelativeFile($file);
                $line = (int) ($location[Keyword::create('line')] ?? 0);
            }

            $phelFunction = new PhelFunction(
                $namespace,
                $this->extractNameWithoutNamespace($fnName),
                $doc,
                $this->prepareRawDoc($doc),
                $matches['signature'] ?? '',
                $matches['desc'] ?? '',
                $groupKey,
                $this->toGithubUrl($file, $line),
                (string) ($meta[Keyword::create('docUrl')] ?? ''),
                $file,
                $line,
            );

            $normalizedFns[$groupKey][$fnName] = $phelFunction;
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
        return static fn (PhelFunction $a, PhelFunction $b): int => (($a->namespace() <=> $b->namespace()) !== 0)
            ? $a->namespace() <=> $b->namespace()
            : ($a->name() <=> $b->name());
    }

    /**
     * @param array<string, PhelFunction> $originalNormalizedFns
     *
     * @return list<PhelFunction>
     */
    private function normalizeNativeSymbols(array $originalNormalizedFns): array
    {
        $result = [];
        foreach ($this->phelFnLoader->getNormalizedNativeSymbols() as $name => $custom) {
            // todo: custom file and line not implemented yet
            $file = $this->toRelativeFile($custom['file'] ?? '');
            $line = $custom['line'] ?? 0;

            $original = $originalNormalizedFns[$name] ?? null;
            $doc = $custom['doc'] ?? $original?->doc() ?? '';
            $namespace = $this->extractNamespace($name);

            $phelFunction = new PhelFunction(
                $namespace,
                $this->extractNameWithoutNamespace($name),
                $doc,
                $this->prepareRawDoc($doc),
                $custom['signature'] ?? $original?->signature() ?? '',
                $custom['desc'] ?? $original?->description() ?? '',
                $this->phelFnGroupKeyGenerator->generateGroupKey($namespace, $name),
                $this->toGithubUrl($file, $line),
                $custom['docUrl'] ?? '',
                $file,
                $line,
            );

            $result[] = $phelFunction;
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
            $fnName = $fn->namespace() . '/' . $fn->name();
            if (isset($seenNames[$fnName])) {
                return false;
            }

            $seenNames[$fnName] = true;
            return true;
        });

        /** @var list<PhelFunction> $result */
        $result = array_values($filtered);

        return $result;
    }

    private function prepareRawDoc(string $doc): string
    {
        $doc = (string) preg_replace('#^```[a-zA-Z]*\r?\n#', '', $doc);
        $doc = (string) preg_replace('#\r?\n```#', '', $doc);

        return trim($doc);
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

        $url = self::GITHUB_BASE_URL . $file;
        if ($line > 0) {
            $url .= '#L' . $line;
        }

        return $url;
    }
}
