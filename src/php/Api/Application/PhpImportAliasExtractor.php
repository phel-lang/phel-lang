<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use function array_merge;
use function count;
use function explode;
use function ltrim;
use function preg_match_all;
use function preg_split;
use function str_replace;
use function trim;

/**
 * Extracts the `short-name => fully-qualified-name` table for PHP classes
 * imported via `(ns ... (:use Foo\Bar :as B))` or top-level `(use ...)`.
 * Detection is lexical so it keeps working on a mid-edit, unparseable buffer.
 */
final class PhpImportAliasExtractor
{
    /**
     * @return array<string, string>
     */
    public function extract(string $source): array
    {
        if (preg_match_all('/\(\s*(?::use|use)\s+([^()]*)\)/', $source, $clauses) === false) {
            return [];
        }

        $aliases = [];
        foreach ($clauses[1] as $clause) {
            $aliases = array_merge($aliases, $this->clauseAliases($clause));
        }

        return $aliases;
    }

    /**
     * @return array<string, string>
     */
    private function clauseAliases(string $clause): array
    {
        $tokens = preg_split('/\s+/', trim($clause)) ?: [];
        $count = count($tokens);
        $aliases = [];
        $i = 0;

        while ($i < $count) {
            $import = $tokens[$i];
            ++$i;

            if ($import === '') {
                continue;
            }

            if ($import[0] === ':') {
                continue;
            }

            $fqn = $this->normalize($import);
            if ($fqn === '') {
                continue;
            }

            [$alias, $i] = $this->aliasFor($tokens, $count, $i, $fqn);
            $aliases[$alias] = $fqn;
        }

        return $aliases;
    }

    /**
     * Resolves the alias for an import: an explicit `:as Alias` when present,
     * otherwise the last `\`-segment of the import. Returns the alias paired
     * with the token index to continue scanning from.
     *
     * @param list<string> $tokens
     *
     * @return array{string, int}
     */
    private function aliasFor(array $tokens, int $count, int $i, string $fqn): array
    {
        if ($i + 1 < $count && $tokens[$i] === ':as') {
            return [$this->normalize($tokens[$i + 1]), $i + 2];
        }

        $segments = explode('\\', $fqn);

        return [end($segments), $i];
    }

    private function normalize(string $name): string
    {
        return str_replace('.', '\\', ltrim($name, '\\'));
    }
}
