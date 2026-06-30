<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use function in_array;
use function strlen;
use function strpos;

/**
 * Splits a slice of Phel source into its top-level whitespace-separated tokens,
 * keeping nested `(...)` forms whole and skipping string literals and `;`
 * comments. Commas count as whitespace (Clojure-aligned). Shared by the interop
 * resolvers, which need to walk a `php/->` chain or a `let` binding lexically.
 *
 * Pass `$balanceCollectionLiterals` to also keep `[...]`/`{...}` whole, so a
 * vector/map argument counts as one token (used by Phel-call signature help).
 */
final class PhpFormTokenizer
{
    /**
     * @return array{0: list<string>, 1: bool} the tokens, and whether the slice
     *                                         ends mid-token (the caret is still inside the last token rather than
     *                                         past it)
     */
    public function topLevel(string $content, bool $balanceCollectionLiterals = false): array
    {
        $opening = $balanceCollectionLiterals ? ['(', '[', '{'] : ['('];
        $closing = $balanceCollectionLiterals ? [')', ']', '}'] : [')'];
        $length = strlen($content);
        $tokens = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $i = 0;

        while ($i < $length) {
            $char = $content[$i];

            if ($inString) {
                $current .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $content[$i + 1];
                    $i += 2;
                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                ++$i;
                continue;
            }

            if ($char === '"') {
                $inString = true;
                $current .= $char;
                ++$i;
                continue;
            }

            if ($char === ';' && $depth === 0) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }

                $newline = strpos($content, "\n", $i);
                if ($newline === false) {
                    return [$tokens, false];
                }

                $i = $newline + 1;
                continue;
            }

            if ($depth === 0 && in_array($char, [' ', "\t", "\n", "\r", ','], true)) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }

                ++$i;
                continue;
            }

            if (in_array($char, $opening, true)) {
                ++$depth;
            } elseif ($depth > 0 && in_array($char, $closing, true)) {
                --$depth;
            }

            $current .= $char;
            ++$i;
        }

        $endsOpen = $current !== '';
        if ($endsOpen) {
            $tokens[] = $current;
        }

        return [$tokens, $endsOpen];
    }
}
