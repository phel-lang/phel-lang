<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Transfer\PhpInteropContext;

use function array_slice;
use function in_array;
use function ltrim;
use function preg_match;
use function preg_quote;
use function str_replace;
use function trim;

/**
 * Resolves the PHP-interop completion context at a cursor position by scanning
 * the source text. Detection is lexical (not analyzer-driven) so it keeps
 * working while the buffer is mid-edit and unparseable; an unrecognised
 * position yields {@see PhpInteropContext::none()} so completion degrades to
 * the normal Phel behaviour.
 *
 * Short class names imported via `(ns ... (:use Foo\Bar :as B))` or top-level
 * `(use ...)` are mapped back to their fully-qualified name (via
 * {@see PhpImportAliasExtractor}) so the reflector can resolve them.
 */
final readonly class PhpInteropContextResolver
{
    public function __construct(
        private PhpImportAliasExtractor $aliasExtractor = new PhpImportAliasExtractor(),
        private PhpInteropReflector $reflector = new PhpInteropReflector(),
        private PhpFormTokenizer $tokenizer = new PhpFormTokenizer(),
    ) {}

    /**
     * @param int $line 1-based line number
     * @param int $col  1-based cursor column
     */
    public function resolve(string $source, int $line, int $col): PhpInteropContext
    {
        $before = CursorText::before($source, $line, $col);

        // Interop-looking text inside a string literal or `;` comment (e.g. a
        // `\Foo` path in a string) is not an interop position.
        if (CursorText::cursorInStringOrComment($before)) {
            return PhpInteropContext::none();
        }

        // (php/-> receiver method|)  or  (php/-> receiver (method|))
        if (preg_match('/\(\s*php\/->\s+(.+?)\s+\(?([A-Za-z0-9_]*)$/s', $before, $m) === 1) {
            return $this->memberContext(PhpInteropContext::KIND_INSTANCE_MEMBER, $m, $source);
        }

        // (php/:: Class method|)  or  (php/:: Class (method|))
        if (preg_match('/\(\s*php\/::\s+(.+?)\s+\(?([A-Za-z0-9_]*)$/s', $before, $m) === 1) {
            return $this->memberContext(PhpInteropContext::KIND_STATIC_MEMBER, $m, $source);
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
     * Builds an instance/static member context from a matched `(php/-> ...)` /
     * `(php/:: ...)` form, resolving the receiver to a class. Yields
     * {@see PhpInteropContext::none()} when the receiver type is unknown.
     *
     * @param array{0: string, 1: string, 2: string} $m
     */
    private function memberContext(string $kind, array $m, string $source): PhpInteropContext
    {
        $aliases = $this->aliasExtractor->extract($source);
        $class = $this->resolveReceiver($m[1], $source, $aliases);

        if ($class === '') {
            return PhpInteropContext::none();
        }

        return new PhpInteropContext($kind, $m[2], $class);
    }

    /**
     * Resolves the receiver of a `php/->`/`php/::` form to a class, walking a
     * chain: the first token is the base receiver and each following
     * `(method ...)` hop advances the class through its reflected return type.
     * A hop whose return type is not a reflectable class yields '' (no context).
     *
     * @param array<string, string> $aliases
     */
    private function resolveReceiver(string $receiver, string $source, array $aliases): string
    {
        [$tokens] = $this->tokenizer->topLevel(trim($receiver));
        if ($tokens === []) {
            return '';
        }

        $class = $this->resolveReceiverClass($tokens[0], $source, $aliases);
        if ($class === '') {
            return '';
        }

        foreach (array_slice($tokens, 1) as $hop) {
            if (preg_match('/^\(\s*([A-Za-z_]\w*)/', $hop, $m) !== 1) {
                return '';
            }

            $class = $this->reflector->methodReturnType($class, $m[1]);
            if ($class === '') {
                return '';
            }
        }

        return $class;
    }

    /**
     * Resolves a receiver expression to a class name. Handles a class literal
     * (`\Foo`, `Foo\Bar`), an inline `(php/new \Foo ...)`, an imported short
     * name (`(:use Foo\Bar)` → `Bar`), or a bare symbol whose `:tag` /
     * reader-tag / `(php/new ...)` binding is found in the source. Returns ''
     * when the type is unknown.
     *
     * @param array<string, string> $aliases
     */
    private function resolveReceiverClass(string $receiver, string $source, array $aliases): string
    {
        $receiver = trim($receiver);

        // Inline construction: (php/-> (php/new \Foo ...) ...)
        if (preg_match('/\(\s*php\/new\s+\\\\?([A-Za-z0-9_\\\\.]+)/', $receiver, $m) === 1) {
            return $this->mapAlias($m[1], $aliases);
        }

        // Class literal kept separate from the bare-symbol branch below so a
        // single unqualified name (e.g. `Widget`) is NOT treated as a literal
        // and can instead resolve through the import-alias table.
        // Multi-segment literal: \Foo\Bar or Foo\Bar.
        if (preg_match('/^\\\\?([A-Za-z_]\w*(?:\\\\[A-Za-z_]\w*)+)$/', $receiver, $m) === 1) {
            return ltrim($m[1], '\\');
        }

        // Single segment with a leading backslash: \Foo.
        if (preg_match('/^\\\\([A-Za-z_]\w*)$/', $receiver, $m) === 1) {
            return $m[1];
        }

        // Bare symbol: an imported short name first, else a typed local binding.
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_\-]*$/', $receiver) === 1) {
            return $aliases[$receiver] ?? $this->resolveSymbolTag($receiver, $source, $aliases);
        }

        return '';
    }

    /**
     * Searches the source for the type of a local binding named `$symbol`:
     *
     * - a `^{:tag \Type}` map or `^\Type` reader tag,
     * - a `[symbol (php/new \Type ...)]` binding,
     * - a `[symbol (php/:: \Type make ...)]` factory binding (the static
     *   method's reflected return type),
     * - a `[symbol other]` indirect binding (the type of `other`).
     *
     * Resolved class literals are mapped through the import-alias table so a
     * `:use`d short name becomes its FQN. `$seen` guards against binding cycles
     * (`[a b b a]`). Returns '' when the type is unknown.
     *
     * @param array<string, string> $aliases
     * @param list<string>          $seen
     */
    private function resolveSymbolTag(string $symbol, string $source, array $aliases, array $seen = []): string
    {
        if (in_array($symbol, $seen, true)) {
            return '';
        }

        $quoted = preg_quote($symbol, '/');

        // ^{:tag \Type} symbol  /  ^{:tag Type} symbol
        if (preg_match('/\^\{[^}]*:tag\s+\\\\?([A-Za-z0-9_\\\\.]+)[^}]*\}\s+' . $quoted . '\b/', $source, $m) === 1) {
            return $this->mapAlias($m[1], $aliases);
        }

        // ^\Type symbol  /  ^Type symbol
        if (preg_match('/\^\\\\?([A-Za-z_][A-Za-z0-9_\\\\.]*)\s+' . $quoted . '\b/', $source, $m) === 1) {
            return $this->mapAlias($m[1], $aliases);
        }

        // [symbol (php/new \Type ...)]  binding
        if (preg_match('/\b' . $quoted . '\s+\(\s*php\/new\s+\\\\?([A-Za-z0-9_\\\\.]+)/', $source, $m) === 1) {
            return $this->mapAlias($m[1], $aliases);
        }

        // [symbol (php/:: \Type make ...)]  factory binding → static return type
        if (preg_match('/\b' . $quoted . '\s+\(\s*php\/::\s+\\\\?([A-Za-z0-9_\\\\.]+)\s+\(?([A-Za-z_]\w*)/', $source, $m) === 1) {
            $class = $this->mapAlias($m[1], $aliases);

            return $class === '' ? '' : $this->reflector->methodReturnType($class, $m[2]);
        }

        // [symbol other-symbol]  indirect binding → follow the alias.
        if (preg_match('/\b' . $quoted . '\s+([A-Za-z_][A-Za-z0-9_\-]*)\b/', $source, $m) === 1) {
            return $this->resolveSymbolTag($m[1], $source, $aliases, [...$seen, $symbol]);
        }

        return '';
    }

    /**
     * Maps a (possibly short, possibly `.`-separated) class name to its
     * fully-qualified `\`-separated form, using the import-alias table when the
     * name is an imported alias and otherwise normalising it as-is.
     *
     * @param array<string, string> $aliases
     */
    private function mapAlias(string $name, array $aliases): string
    {
        $normalized = str_replace('.', '\\', ltrim($name, '\\'));

        return $aliases[$normalized] ?? $normalized;
    }
}
