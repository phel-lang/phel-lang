<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Transfer\PhpInteropContext;

use function count;
use function explode;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_split;
use function str_replace;
use function trim;

/**
 * Resolves the PHP-interop completion context at a cursor position by scanning
 * the source text. Detection is lexical (not analyzer-driven) so it keeps
 * working while the buffer is mid-edit and unparseable; an unrecognised
 * position yields {@see PhpInteropContext::none()} so completion degrades to
 * the normal Phel behaviour.
 *
 * Short class names imported via `(ns ... (:use Foo\Bar [:as B]))` or top-level
 * `(use ...)` are mapped back to their fully-qualified name so the reflector can
 * resolve them.
 */
final readonly class PhpInteropContextResolver
{
    /**
     * @param int $line 1-based line number
     * @param int $col  1-based cursor column
     */
    public function resolve(string $source, int $line, int $col): PhpInteropContext
    {
        $before = CursorText::before($source, $line, $col);

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
     * (`\Foo`, `Foo\Bar`), an inline `(php/new \Foo ...)`, an imported short
     * name (`(:use Foo\Bar)` → `Bar`), or a bare symbol whose `:tag` /
     * reader-tag / `(php/new ...)` binding is found in the source. Returns ''
     * when the type is unknown.
     */
    private function resolveReceiverClass(string $receiver, string $source): string
    {
        $receiver = trim($receiver);

        // Inline construction: (php/-> (php/new \Foo ...) ...)
        if (preg_match('/\(\s*php\/new\s+\\\\?([A-Za-z0-9_\\\\.]+)/', $receiver, $m) === 1) {
            return $this->mapAlias($m[1], $source);
        }

        // Class literal: \Foo, \Foo\Bar, or Foo\Bar.
        if (preg_match('/^\\\\?([A-Za-z_]\w*(?:\\\\[A-Za-z_]\w*)+)$/', $receiver, $m) === 1) {
            return ltrim($m[1], '\\');
        }

        if (preg_match('/^\\\\([A-Za-z_]\w*)$/', $receiver, $m) === 1) {
            return $m[1];
        }

        // Bare symbol: an imported short name first, else a typed local binding.
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_\-]*$/', $receiver) === 1) {
            $imported = $this->aliasMap($source)[$receiver] ?? '';
            if ($imported !== '') {
                return $imported;
            }

            return $this->resolveSymbolTag($receiver, $source);
        }

        return '';
    }

    /**
     * Searches the source for a type annotation of a local binding named
     * `$symbol`: a `^{:tag \Type}` map, a `^\Type` reader tag, or a
     * `[symbol (php/new \Type ...)]` binding. The resolved name is mapped
     * through the import-alias table so a `:use`d short name becomes its FQN.
     */
    private function resolveSymbolTag(string $symbol, string $source): string
    {
        $quoted = preg_quote($symbol, '/');
        $name = '';

        // ^{:tag \Type} symbol  /  ^{:tag Type} symbol
        if (preg_match('/\^\{[^}]*:tag\s+\\\\?([A-Za-z0-9_\\\\.]+)[^}]*\}\s+' . $quoted . '\b/', $source, $m) === 1) {
            $name = $m[1];
        } elseif (preg_match('/\^\\\\?([A-Za-z_][A-Za-z0-9_\\\\.]*)\s+' . $quoted . '\b/', $source, $m) === 1) {
            // ^\Type symbol  /  ^Type symbol
            $name = $m[1];
        } elseif (preg_match('/\b' . $quoted . '\s+\(\s*php\/new\s+\\\\?([A-Za-z0-9_\\\\.]+)/', $source, $m) === 1) {
            // [symbol (php/new \Type ...)]  binding
            $name = $m[1];
        }

        if ($name === '') {
            return '';
        }

        return $this->mapAlias($name, $source);
    }

    /**
     * Maps a (possibly short, possibly `.`-separated) class name to its
     * fully-qualified `\`-separated form, using the import-alias table when the
     * name is an imported alias and otherwise normalising it as-is.
     */
    private function mapAlias(string $name, string $source): string
    {
        $normalized = str_replace('.', '\\', ltrim($name, '\\'));

        return $this->aliasMap($source)[$normalized] ?? $normalized;
    }

    /**
     * Builds the `short-name => FQN` table from every `(:use ...)` (inside an
     * `ns` form) and top-level `(use ...)` clause in the source. Honours
     * `:as Alias`; otherwise the alias is the last `\`-segment of the import.
     *
     * @return array<string, string>
     */
    private function aliasMap(string $source): array
    {
        $map = [];

        if (preg_match_all('/\(\s*(?::use|use)\s+([^()]*)\)/', $source, $clauses) === false) {
            return $map;
        }

        foreach ($clauses[1] as $clause) {
            $this->collectAliases($clause, $map);
        }

        return $map;
    }

    /**
     * @param array<string, string> $map
     */
    private function collectAliases(string $clause, array &$map): void
    {
        $tokens = preg_split('/\s+/', trim($clause)) ?: [];
        $count = count($tokens);
        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i];
            ++$i;
            if ($token === '') {
                continue;
            }

            if ($token[0] === ':') {
                continue;
            }

            $fqn = str_replace('.', '\\', ltrim($token, '\\'));
            if ($fqn === '') {
                continue;
            }

            $alias = '';
            if ($i + 1 < $count && $tokens[$i] === ':as') {
                $alias = $tokens[$i + 1];
                $i += 2;
            }

            if ($alias === '') {
                $parts = explode('\\', $fqn);
                $alias = $parts[count($parts) - 1];
            }

            $map[str_replace('.', '\\', ltrim($alias, '\\'))] = $fqn;
        }
    }
}
