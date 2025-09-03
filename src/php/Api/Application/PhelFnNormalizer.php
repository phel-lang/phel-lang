<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Api\Domain\PhelFnNormalizerInterface;
use Phel\Api\Transfer\PhelFunction;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;

final readonly class PhelFnNormalizer implements PhelFnNormalizerInterface
{
    private const string GITHUB_BASE_URL = 'https://github.com/phel-lang/phel-lang/blob/main/';

    /**
     * @param list<string> $allNamespaces
     */
    public function __construct(
        private PhelFnLoaderInterface $phelFnLoader,
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
            $docUrl = (string) ($meta[Keyword::create('docUrl')] ?? '');
            $pattern = '#(```phel\n(?<fnSignature>.*)\n```\n)?(?<desc>.*)#s';
            preg_match($pattern, (string) $doc, $matches);
            $groupKey = $this->groupKey($fnName);
            $fnNs = $this->fnNamespace($fnName);
            $file = '';
            $line = 0;
            $location = $meta[Keyword::create('start-location')] ?? null;
            if ($location instanceof PersistentMapInterface) {
                $file = (string) ($location[Keyword::create('file')] ?? '');
                $line = (int) ($location[Keyword::create('line')] ?? 0);
            }

            $file = $this->toRelativeFile($file);
            $githubUrl = $this->toGithubUrl($file, $line);

            $normalizedFns[$groupKey][$fnName] = new PhelFunction(
                $fnName,
                $doc,
                $matches['fnSignature'] ?? '',
                $matches['desc'] ?? '',
                $groupKey,
                $githubUrl,
                $docUrl,
                $file,
                $line,
                $fnNs,
            );
        }

        foreach ($normalizedFns as $values) {
            usort($values, $this->sortingPhelFunctionsCallback());
        }

        $originalValues = array_values($normalizedFns);

        $result = array_merge(
            $this->normalizeNativeSymbols(array_merge(...$originalValues)),
            ...$originalValues,
        );

        usort($result, $this->sortingPhelFunctionsCallback());

        return $this->removeDuplicates($result);
    }

    private function groupKey(string $fnName): string
    {
        $key = preg_replace(
            '/[^a-zA-Z0-9\-]+/',
            '',
            str_replace('/', '-', $fnName),
        );

        return strtolower(rtrim((string) $key, '-'));
    }

    private function fnNamespace(string $fnName): string
    {
        $pos = strrpos($fnName, '/');

        return $pos === false ? '' : substr($fnName, 0, $pos);
    }

    private function sortingPhelFunctionsCallback(): callable
    {
        return static fn (PhelFunction $a, PhelFunction $b): int => $a->fnName() <=> $b->fnName();
    }

    /**
     * @param array<string, PhelFunction> $originalNormalizedFns
     *
     * @return list<PhelFunction>
     */
    private function normalizeNativeSymbols(array $originalNormalizedFns): array
    {
        $result = [];
        foreach ($this->phelFnLoader->getNormalizedNativeSymbols() as $name => $meta) {
            $file = $this->toRelativeFile($meta['file'] ?? '');
            $line = $meta['line'] ?? 0;
            $docUrl = $meta['docUrl'] ?? '';
            $githubUrl = $this->toGithubUrl($file, $line);
            $fnNs = $this->fnNamespace($name);

            $result[] = new PhelFunction(
                $name,
                $meta['doc'] ?? $originalNormalizedFns[$name]->doc(),
                $meta['fnSignature'] ?? $originalNormalizedFns[$name]->fnSignature(),
                $meta['desc'] ?? $originalNormalizedFns[$name]->description(),
                $this->groupKey($name),
                $githubUrl,
                $docUrl,
                $file,
                $line,
                $fnNs,
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
            $fnName = $fn->fnName();
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
