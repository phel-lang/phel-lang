<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use function str_contains;

/**
 * Pure helper shared by `phel run` / `phel test` shell completion: narrows a
 * list of known namespaces to those matching the partial value typed so far.
 */
final readonly class NamespaceCompletionFilter
{
    /**
     * @param list<string> $namespaces
     *
     * @return list<string>
     */
    public static function matching(array $namespaces, string $typed): array
    {
        if ($typed === '') {
            return $namespaces;
        }

        return array_values(array_filter(
            $namespaces,
            static fn(string $namespace): bool => str_contains($namespace, $typed),
        ));
    }
}
