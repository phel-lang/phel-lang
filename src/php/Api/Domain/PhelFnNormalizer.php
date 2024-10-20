<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Api\Infrastructure\PhelFnLoaderInterface;
use Phel\Api\Transfer\PhelFunction;
use Phel\Lang\Keyword;

final readonly class PhelFnNormalizer implements PhelFnNormalizerInterface
{
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

        $result = [];
        foreach ($normalizedData as $fnName => $meta) {
            $isPrivate = $meta[Keyword::create('private')] ?? false;
            if ($isPrivate) {
                continue;
            }

            $doc = $meta[Keyword::create('doc')] ?? '';
            $pattern = '#(```phel\n(?<fnSignature>.*)\n```\n)?(?<desc>.*)#s';
            preg_match($pattern, (string) $doc, $matches);
            $groupKey = $this->groupKey($fnName);

            $result[$groupKey][] = new PhelFunction(
                $fnName,
                $doc,
                $matches['fnSignature'] ?? '',
                $matches['desc'] ?? '',
                $groupKey,
            );
        }

        foreach ($result as $values) {
            usort($values, $this->sortingPhelFunctionsCallback());
        }

        return array_merge(...array_values($result));
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
}
