<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use function count;
use function is_array;

/**
 * Removes the `\Phel::locationMeta(...)` argument from every
 * `\Phel::addDefinition(...)` call in compiled build output.
 *
 * Def metadata (source locations, docstrings, arglists, arity info,
 * tags) is consumed at COMPILE time — the analyzer reads it from the
 * in-process registry while later namespaces build — but a deployed
 * artifact only executes the definitions, so the meta is load-time dead
 * weight there (measured on this repo's own build: −28% artifact size,
 * −40% cold require time, −2MB peak memory).
 *
 * Token-based so parens inside docstring literals cannot desynchronise
 * the argument boundary. Only the exact `, \Phel::locationMeta( ... )`
 * argument sequence is dropped; everything else passes through verbatim.
 *
 * Trade-offs of a stripped artifact (opt-in, see `strip-symbol-meta`):
 * runtime doc/meta introspection (`phel doc`, `(meta ...)`) over its defs
 * returns nil, and it must not be reused as a compile cache — the build
 * pipeline forces a full recompile when the flag flips (see
 * `ProjectCompiler`).
 */
final readonly class SymbolMetaStripper
{
    private function __construct() {}

    public static function strip(string $phpCode): string
    {
        $tokens = token_get_all('<?php ' . $phpCode);
        $n = count($tokens);
        $out = [];
        // token_get_all injected the opening tag as the first token; skip it.
        for ($i = 1; $i < $n; ++$i) {
            $t = $tokens[$i];

            if ($t === ',' && ($skipTo = self::metaArgumentEnd($tokens, $n, $i)) !== null) {
                $i = $skipTo;
                continue;
            }

            $out[] = is_array($t) ? $t[1] : $t;
        }

        return implode('', $out);
    }

    /**
     * When the token at `$commaIndex` starts a `, \Phel::locationMeta( ... )`
     * argument, returns the index of its closing paren; `null` otherwise.
     *
     * @param list<array{int, string, int}|string> $tokens
     */
    private static function metaArgumentEnd(array $tokens, int $tokenCount, int $commaIndex): ?int
    {
        $j = $commaIndex + 1;
        while ($j < $tokenCount && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            ++$j;
        }

        if (
            $j + 3 >= $tokenCount
            || !is_array($tokens[$j]) || $tokens[$j][1] !== '\Phel'
            || !is_array($tokens[$j + 1]) || $tokens[$j + 1][0] !== T_DOUBLE_COLON
            || !is_array($tokens[$j + 2]) || $tokens[$j + 2][1] !== 'locationMeta'
            || $tokens[$j + 3] !== '('
        ) {
            return null;
        }

        $depth = 0;
        for ($k = $j + 3; $k < $tokenCount; ++$k) {
            if ($tokens[$k] === '(') {
                ++$depth;
            } elseif ($tokens[$k] === ')') {
                --$depth;
                if ($depth === 0) {
                    return $k;
                }
            }
        }

        return null;
    }
}
