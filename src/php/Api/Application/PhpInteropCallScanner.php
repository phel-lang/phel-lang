<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Transfer\PhpInteropCall;

use function count;
use function in_array;
use function max;
use function preg_match;
use function strlen;
use function strpos;
use function substr;

/**
 * Locates the PHP-interop call enclosing the cursor by balancing parentheses
 * (skipping string literals and `;` comments) instead of matching a regex.
 *
 * The structural scan is what fixes chained calls: in
 * `(php/-> recv (a "x") (b "y") (c ⟂` a lazy regex latches onto the first
 * `( method ` segment, but balancing reports the innermost open call `c` and
 * its enclosing `php/->`/`php/::` form, together with the argument index the
 * caret sits on for `activeParameter`.
 */
final class PhpInteropCallScanner
{
    public function scan(string $before): PhpInteropCall
    {
        $open = CursorText::openParenPositions($before);
        $depth = count($open);
        if ($depth === 0) {
            return PhpInteropCall::none();
        }

        [$innerTokens, $innerEndsOpen] = $this->splitTopLevel(substr($before, $open[$depth - 1] + 1));
        if ($innerTokens === []) {
            return PhpInteropCall::none();
        }

        $head = $innerTokens[0];

        if ($head === 'php/new') {
            return $this->constructorCall($innerTokens, $innerEndsOpen);
        }

        if (preg_match('/^\w+$/', $head) !== 1 || $depth < 2) {
            return PhpInteropCall::none();
        }

        return $this->methodCall($before, $open[$depth - 2], $head, $innerTokens, $innerEndsOpen);
    }

    /**
     * @param non-empty-list<string> $tokens
     */
    private function constructorCall(array $tokens, bool $endsOpen): PhpInteropCall
    {
        if (!isset($tokens[1])) {
            return PhpInteropCall::none();
        }

        // tokens = [php/new, Class, arg0, arg1, ...]: drop the `php/new` head and
        // the class token to land on the constructor's own argument index.
        $active = max(0, count($tokens) - 2 - ($endsOpen ? 1 : 0));

        return new PhpInteropCall(PhpInteropCall::KIND_CONSTRUCTOR, $tokens[1], '', $active);
    }

    /**
     * @param non-empty-list<string> $innerTokens
     */
    private function methodCall(
        string $before,
        int $parentPos,
        string $method,
        array $innerTokens,
        bool $innerEndsOpen,
    ): PhpInteropCall {
        [$parentTokens] = $this->splitTopLevel(substr($before, $parentPos + 1));
        $parentHead = $parentTokens[0] ?? '';
        if ($parentHead !== 'php/->' && $parentHead !== 'php/::') {
            return PhpInteropCall::none();
        }

        $receiver = $parentTokens[1] ?? '';
        if ($receiver === '') {
            return PhpInteropCall::none();
        }

        // tokens = [method, arg0, arg1, ...]: drop the method head only.
        $active = max(0, count($innerTokens) - 1 - ($innerEndsOpen ? 1 : 0));

        return new PhpInteropCall(PhpInteropCall::KIND_METHOD, $receiver, $method, $active);
    }

    /**
     * Splits a frame's content into its top-level tokens (nested forms kept
     * whole, string literals and `;` comments skipped, commas treated as
     * whitespace) and reports whether the content ends mid-token, i.e. the caret
     * is still inside the last argument rather than past it.
     *
     * @return array{0: list<string>, 1: bool}
     */
    private function splitTopLevel(string $content): array
    {
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

            if ($depth === 0 && (in_array($char, [' ', "\t", "\n", "\r", ','], true))) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }

                ++$i;
                continue;
            }

            if ($char === '(') {
                ++$depth;
            } elseif ($char === ')' && $depth > 0) {
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
