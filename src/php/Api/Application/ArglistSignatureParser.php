<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Turns the `arglists` metadata a `def` carries into displayable signatures.
 *
 * `DefArglistBuilder` writes one of two shapes, both as a single-line string:
 *
 * - single arity, the parameter vector on its own: `[f & colls]`
 * - multi arity, every vector wrapped in one pair of parens:
 *   `([] [a] [a b] [a b c] [a b c & more])`
 *
 * A `defn` never needs this. The macro renders the same arities into a fenced
 * block at the top of the docstring, and {@see DocstringSignatureParser} reads
 * them from there. A bare `def` over an `fn` has no such block, which is why
 * `(def list ... (fn ([] ...) ([a] ...)))` reported no signature at all while
 * `hash-set` reported five (#3012). The metadata was always correct; nothing
 * on this path read it.
 *
 * @internal
 */
final readonly class ArglistSignatureParser
{
    /**
     * @return list<string>
     */
    public static function parse(string $arglists, string $name): array
    {
        $trimmed = trim($arglists);
        if ($trimmed === '') {
            return [];
        }

        $vectors = str_starts_with($trimmed, '(')
            ? self::splitVectors(trim(substr($trimmed, 1, -1)))
            : [$trimmed];

        $signatures = [];
        foreach ($vectors as $vector) {
            // Exactly one bracket off each end. `trim($vector, '[]')` would eat
            // the brackets of a destructuring parameter too, turning
            // `[[a b] [c d]]` into `a b] [c d`.
            $params = trim(substr(trim($vector), 1, -1));

            $signatures[] = $params === ''
                ? sprintf('(%s)', $name)
                : sprintf('(%s %s)', $name, $params);
        }

        return $signatures;
    }

    /**
     * Splits `[] [a] [[a b] c]` into its top-level vectors.
     *
     * Bracket depth rather than a split on `] [`, because a destructuring
     * parameter nests: `[[a b] c]` is one arity, not two.
     *
     * @return list<string>
     */
    private static function splitVectors(string $input): array
    {
        $vectors = [];
        $depth = 0;
        $start = 0;

        for ($i = 0, $length = strlen($input); $i < $length; ++$i) {
            $char = $input[$i];

            if ($char === '[') {
                if ($depth === 0) {
                    $start = $i;
                }

                ++$depth;
                continue;
            }

            if ($char === ']') {
                --$depth;
                if ($depth === 0) {
                    $vectors[] = substr($input, $start, $i - $start + 1);
                }
            }
        }

        // An unbalanced string means the metadata is not the shape this parser
        // knows. Reporting nothing lets the caller keep its existing fallback
        // rather than publishing a mangled signature.
        return $depth === 0 && $vectors !== [] ? $vectors : [];
    }
}
