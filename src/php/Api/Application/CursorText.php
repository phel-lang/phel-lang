<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use function array_pop;
use function array_slice;
use function implode;
use function max;
use function preg_match;
use function preg_split;
use function strlen;
use function strpos;
use function substr;

/**
 * Small text helpers shared by the PHP-interop resolvers for working with a
 * 1-based (line, col) cursor over a source string.
 */
final class CursorText
{
    /**
     * The source up to (not including) the cursor, trimmed to the start of the
     * outermost interop form still open at the cursor. Spanning multiple lines
     * lets the resolver see a `(php/-> recv\n  (method ...))` whose opener sits
     * on an earlier line; trimming to the enclosing form stops an earlier,
     * already-closed sibling form from hijacking the (anchored, lazy) regexes.
     */
    public static function before(string $source, int $line, int $col): string
    {
        $lines = preg_split('/\r?\n/', $source) ?: [];
        if (!isset($lines[$line - 1])) {
            return '';
        }

        $prefixLines = array_slice($lines, 0, $line - 1);
        $prefixLines[] = substr($lines[$line - 1], 0, max(0, $col - 1));

        return self::fromEnclosingForm(implode("\n", $prefixLines));
    }

    /**
     * Whether the cursor sits inside a string literal or a `;` line comment,
     * given the {@see self::before()} prefix. Used to suppress interop detection
     * there, since the prefix may contain interop-looking text (`\Foo`,
     * `php/->`) that is really part of a string or comment. The prefix begins at
     * an open `(` found outside any string/comment, so scanning it from the
     * start reflects the cursor's state correctly.
     */
    public static function cursorInStringOrComment(string $before): bool
    {
        $length = strlen($before);
        $inString = false;
        $i = 0;

        while ($i < $length) {
            $char = $before[$i];

            if ($inString) {
                if ($char === '\\' && $i + 1 < $length) {
                    // Skip the escaped character so `\"` does not close the string.
                    $i += 2;
                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }
            } elseif ($char === '"') {
                $inString = true;
            } elseif ($char === ';') {
                $newline = strpos($before, "\n", $i);
                if ($newline === false) {
                    // The comment runs unbroken to the cursor.
                    return true;
                }

                $i = $newline + 1;
                continue;
            }

            ++$i;
        }

        return $inString;
    }

    /**
     * The column just past the identifier the cursor sits on, so a resolver
     * sees the whole word rather than only the part before the caret.
     */
    public static function wordEndColumn(string $source, int $line, int $col): int
    {
        $lines = preg_split('/\r?\n/', $source) ?: [];
        $text = $lines[$line - 1] ?? '';
        $offset = max(0, $col - 1);

        if (preg_match('/[A-Za-z0-9_]+/A', substr($text, $offset), $m) === 1) {
            return $col + strlen($m[0]);
        }

        return $col;
    }

    /**
     * Indices of every `(` still open at the end of `$prefix`, outermost first.
     * String literals and `;` line comments are skipped while balancing. Only
     * parens are tracked (not `[`/`{`): the interop forms are paren-delimited,
     * so brackets/braces do not affect which `(` is open.
     *
     * @return list<int>
     */
    public static function openParenPositions(string $prefix): array
    {
        $length = strlen($prefix);
        $openParens = [];
        $inString = false;
        $i = 0;

        while ($i < $length) {
            $char = $prefix[$i];

            if ($inString) {
                if ($char === '\\') {
                    $i += 2;
                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }
            } elseif ($char === '"') {
                $inString = true;
            } elseif ($char === ';') {
                $newline = strpos($prefix, "\n", $i);
                if ($newline === false) {
                    break;
                }

                $i = $newline + 1;
                continue;
            } elseif ($char === '(') {
                $openParens[] = $i;
            } elseif ($char === ')') {
                array_pop($openParens);
            }

            ++$i;
        }

        return $openParens;
    }

    /**
     * Returns `$prefix` from the position of the outermost `(` that is still
     * open at its end, so callers only see the form the cursor is inside.
     */
    private static function fromEnclosingForm(string $prefix): string
    {
        $openParens = self::openParenPositions($prefix);
        if ($openParens === []) {
            return $prefix;
        }

        return substr($prefix, $openParens[0]);
    }
}
