<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Api\Domain\PhelFnNormalizerInterface;
use Phel\Api\Transfer\PhelFunction;
use Phel\Lang\Keyword;

use function in_array;

final readonly class PhelFnNormalizer implements PhelFnNormalizerInterface
{
    /**
     * @param  list<string>  $allNamespaces
     */
    public function __construct(
        private PhelFnLoaderInterface $phelFnLoader,
        private array $allNamespaces = [],
    ) {
    }

    /**
     * @param  list<string>  $namespaces
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
            $pattern = '#(```phel\n(?<fnSignature>.*)\n```\n)?(?<desc>.*)#s';
            preg_match($pattern, (string) $doc, $matches);
            $groupKey = $this->groupKey($fnName);

            $normalizedFns[$groupKey][$fnName] = new PhelFunction(
                $fnName,
                $doc,
                $matches['fnSignature'] ?? '',
                $matches['desc'] ?? '',
                $groupKey,
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

    private function sortingPhelFunctionsCallback(): callable
    {
        return static fn (PhelFunction $a, PhelFunction $b): int => $a->fnName() <=> $b->fnName();
    }

    /**
     * @param array<string, PhelFunction> $originalNormalizedFns
     *
     * @return  list<PhelFunction>
     */
    private function normalizeNativeSymbols(array $originalNormalizedFns): array
    {
        $result = [];
        foreach ($this->phelFnLoader->getNormalizedNativeSymbols() as $name => $meta) {
            $result[] = new PhelFunction(
                $name,
                $meta['doc'] ?? $originalNormalizedFns[$name]->doc(),
                $meta['fnSignature'] ?? $originalNormalizedFns[$name]->fnSignature(),
                $meta['desc'] ?? $originalNormalizedFns[$name]->description(),
                $this->groupKey($name),
                $meta['url'] ?? '',
            );
        }

        return $result;
    }

    /**
     * @param  list<PhelFunction>  $fns
     *
     * @return list<PhelFunction>
     */
    private function removeDuplicates(array $fns): array
    {
        $seenNames = [];

        return array_filter($fns, static function (PhelFunction $fn) use (&$seenNames): bool {
            $fnName = $fn->fnName();
            if (in_array($fnName, $seenNames, true)) {
                return false;
            }

            $seenNames[] = $fnName;
            return true;
        });
    }
}
