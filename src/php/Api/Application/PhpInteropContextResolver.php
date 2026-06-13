<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Transfer\PhpInteropContext;

use function ltrim;
use function max;
use function preg_match;
use function preg_quote;
use function preg_split;
use function substr;
use function trim;

/**
 * Resolves the PHP-interop completion context at a cursor position by scanning
 * the source text. Detection is lexical (not analyzer-driven) so it keeps
 * working while the buffer is mid-edit and unparseable; an unrecognised
 * position yields {@see PhpInteropContext::none()} so completion degrades to
 * the normal Phel behaviour.
 */
final readonly class PhpInteropContextResolver
{
    /**
     * @param int $line 1-based line number
     * @param int $col  1-based cursor column
     */
    public function resolve(string $source, int $line, int $col): PhpInteropContext
    {
        $before = $this->textBeforeCursor($source, $line, $col);

        // (php/-> receiver method|)  or  (php/-> receiver (method|))
        if (preg_match('/\(\s*php\/->\s+(.+?)\s+\(?([A-Za-z0-9_]*)$/s', $before, $m) === 1) {
            $class = $this->resolveReceiverClass($m[1], $source);
            if ($class !== '') {
                return new PhpInteropContext(PhpInteropContext::KIND_INSTANCE_MEMBER, $m[2], $class);
            }

            return PhpInteropContext::none();
        }

        // (php/:: Class method|)  or  (php/:: Class (method|))
        if (preg_match('/\(\s*php\/::\s+(.+?)\s+\(?([A-Za-z0-9_]*)$/s', $before, $m) === 1) {
            $class = $this->resolveReceiverClass($m[1], $source);
            if ($class !== '') {
                return new PhpInteropContext(PhpInteropContext::KIND_STATIC_MEMBER, $m[2], $class);
            }

            return PhpInteropContext::none();
        }

        // (php/new \Foo|
        if (preg_match('/\(\s*php\/new\s+\\\\?([A-Za-z0-9_\\\\]*)$/', $before, $m) === 1) {
            return new PhpInteropContext(PhpInteropContext::KIND_CLASS_NAME, $m[1]);
        }

        // A fully-qualified \Foo\Bar position anywhere.
        if (preg_match('/\\\\([A-Za-z0-9_\\\\]*)$/', $before, $m) === 1) {
            return new PhpInteropContext(PhpInteropContext::KIND_CLASS_NAME, $m[1]);
        }

        // php/<fn> global function (excluding the interop special forms).
        if (preg_match('/(?:^|[\s(\[{])php\/(\w+)$/', $before, $m) === 1 && $m[1] !== 'new') {
            return new PhpInteropContext(PhpInteropContext::KIND_GLOBAL_FUNCTION, $m[1]);
        }

        return PhpInteropContext::none();
    }

    /**
     * Resolves a receiver expression to a class name. Handles a class literal
     * (`\Foo`, `Foo\Bar`), an inline `(php/new \Foo ...)`, or a bare symbol
     * whose `:tag` / reader-tag / `(php/new ...)` binding is found in the
     * source. Returns '' when the type is unknown.
     */
    private function resolveReceiverClass(string $receiver, string $source): string
    {
        $receiver = trim($receiver);

        // Inline construction: (php/-> (php/new \Foo ...) ...)
        if (preg_match('/\(\s*php\/new\s+\\\\?([A-Za-z0-9_\\\\]+)/', $receiver, $m) === 1) {
            return ltrim($m[1], '\\');
        }

        // Class literal: \Foo, \Foo\Bar, or Foo\Bar.
        if (preg_match('/^\\\\?([A-Za-z_]\w*(?:\\\\[A-Za-z_]\w*)+)$/', $receiver, $m) === 1) {
            return ltrim($m[1], '\\');
        }

        if (preg_match('/^\\\\([A-Za-z_]\w*)$/', $receiver, $m) === 1) {
            return $m[1];
        }

        // Bare symbol: look up its type from the surrounding source.
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_\-]*$/', $receiver) === 1) {
            return $this->resolveSymbolTag($receiver, $source);
        }

        return '';
    }

    /**
     * Searches the source for a type annotation of a local binding named
     * `$symbol`: a `^{:tag \Type}` map, a `^\Type` reader tag, or a
     * `[symbol (php/new \Type ...)]` binding.
     */
    private function resolveSymbolTag(string $symbol, string $source): string
    {
        $quoted = preg_quote($symbol, '/');

        // ^{:tag \Type} symbol  /  ^{:tag Type} symbol
        if (preg_match('/\^\{[^}]*:tag\s+\\\\?([A-Za-z0-9_\\\\]+)[^}]*\}\s+' . $quoted . '\b/', $source, $m) === 1) {
            return ltrim($m[1], '\\');
        }

        // ^\Type symbol  /  ^Type symbol
        if (preg_match('/\^\\\\?([A-Za-z_][A-Za-z0-9_\\\\]*)\s+' . $quoted . '\b/', $source, $m) === 1) {
            return ltrim($m[1], '\\');
        }

        // [symbol (php/new \Type ...)]  binding
        if (preg_match('/\b' . $quoted . '\s+\(\s*php\/new\s+\\\\?([A-Za-z0-9_\\\\]+)/', $source, $m) === 1) {
            return ltrim($m[1], '\\');
        }

        return '';
    }

    private function textBeforeCursor(string $source, int $line, int $col): string
    {
        $lines = preg_split('/\r?\n/', $source) ?: [];
        if (!isset($lines[$line - 1])) {
            return '';
        }

        return substr($lines[$line - 1], 0, max(0, $col - 1));
    }
}
