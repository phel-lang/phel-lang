<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use function max;
use function preg_match;
use function preg_split;
use function strlen;
use function substr;

/**
 * Small text helpers shared by the PHP-interop resolvers for working with a
 * 1-based (line, col) cursor over a source string.
 */
final class CursorText
{
    /**
     * The text on `$line` up to (not including) the cursor column.
     */
    public static function before(string $source, int $line, int $col): string
    {
        $lines = preg_split('/\r?\n/', $source) ?: [];
        if (!isset($lines[$line - 1])) {
            return '';
        }

        return substr($lines[$line - 1], 0, max(0, $col - 1));
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
}
