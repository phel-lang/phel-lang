<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Transfer\PhpInteropContext;

use function array_map;
use function array_reverse;
use function array_slice;
use function in_array;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
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
 *
 * @internal
 */
final readonly class PhpInteropContextResolver
{
    /**
     * Word-form `php/<name>` interop special forms (array/object/new/ref/
     * callable): not PHP global functions, so they must not route to global-
     * function completion. Mirrors the `Symbol::NAME_PHP_*` constants whose
     * value has no `-`/symbolic suffix.
     */
    private const array INTEROP_SPECIAL_FORMS = [
        'new', 'aget', 'aset', 'apush', 'aunset', 'ref', 'callable', 'oset',
    ];

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
            return $this->memberContext(PhpInteropContext::KIND_INSTANCE_MEMBER, $m, $source, $before);
        }

        // (php/:: Class method|)  or  (php/:: Class (method|))
        if (preg_match('/\(\s*php\/::\s+(.+?)\s+\(?([A-Za-z0-9_]*)$/s', $before, $m) === 1) {
            return $this->memberContext(PhpInteropContext::KIND_STATIC_MEMBER, $m, $source, $before);
        }

        // \Foo/member| and (\Foo/member| , the source spelling of `php/::`
        // (ADR 0007). The class sits before the cursor, so the same receiver
        // resolution applies; `$prop` is a static property (ADR 0013).
        if (preg_match('/(?:^|[\s(\[{])(\\\\?[A-Za-z_][A-Za-z0-9_\\\\.]*)\/(\$?\w*)$/', $before, $m) === 1
            && $this->isClassReference($m[1])
        ) {
            return $this->memberContext(PhpInteropContext::KIND_STATIC_MEMBER, $m, $source, $before);
        }

        // (.method receiver| and (.-field receiver| , where the receiver
        // follows the cursor rather than preceding it.
        if (preg_match('/\(\s*\.(-?)(\w*)$/', $before, $m) === 1) {
            return $this->dotMemberContext($m[2], $source, $before, $line, $col);
        }

        // (new Foo| , (new \Foo| and the macro-output spelling (php/new \Foo|
        if (preg_match('/\(\s*(?:php\/)?new\s+\\\\?([A-Za-z0-9_\\\\]*)$/', $before, $m) === 1) {
            return new PhpInteropContext(PhpInteropContext::KIND_CLASS_NAME, $m[1]);
        }

        // A fully-qualified \Foo\Bar position anywhere.
        if (preg_match('/\\\\([A-Za-z0-9_\\\\]*)$/', $before, $m) === 1) {
            return new PhpInteropContext(PhpInteropContext::KIND_CLASS_NAME, $m[1]);
        }

        // php/$<name> PHP global variable. This is distinct from the function
        // path because PHP variable names include a leading `$`.
        if (preg_match('/(?:^|[\s(\[{])php\/(\$\w*)$/', $before, $m) === 1) {
            return new PhpInteropContext(PhpInteropContext::KIND_GLOBAL_VARIABLE, $m[1]);
        }

        // php/<fn> global function (excluding the interop special forms).
        if (preg_match('/(?:^|[\s(\[{])php\/(\w+)$/', $before, $m) === 1
            && !in_array($m[1], self::INTEROP_SPECIAL_FORMS, true)) {
            return new PhpInteropContext(PhpInteropContext::KIND_GLOBAL_FUNCTION, $m[1]);
        }

        return PhpInteropContext::none();
    }

    /**
     * A namespace part that names a PHP class rather than a Phel namespace:
     * the analyzer's rule, restated lexically here because completion runs on
     * text the compiler has not seen. `php/` is the host-function prefix and
     * has its own context.
     */
    private function isClassReference(string $token): bool
    {
        if ($token === '' || $token === 'php') {
            return false;
        }

        return $token[0] === '\\'
            || ($token[0] >= 'A' && $token[0] <= 'Z');
    }

    /**
     * `(.method receiver|` and `(.-field receiver|`. The dot shorthands put the
     * member before the receiver, so unlike every other position the class has
     * to come from the text *after* the cursor: the first token of what is
     * already typed there.
     */
    private function dotMemberContext(string $prefix, string $source, string $before, int $line, int $col): PhpInteropContext
    {
        $receiver = CursorText::firstTokenAfter($source, $line, $col);
        if ($receiver === '') {
            return PhpInteropContext::none();
        }

        $class = $this->resolveReceiver($receiver, $before, $this->aliasExtractor->extract($source));
        if ($class === '') {
            return PhpInteropContext::none();
        }

        return new PhpInteropContext(PhpInteropContext::KIND_INSTANCE_MEMBER, $prefix, $class);
    }

    /**
     * Builds an instance/static member context from a matched `(php/-> ...)` /
     * `(php/:: ...)` form, resolving the receiver to a class. Yields
     * {@see PhpInteropContext::none()} when the receiver type is unknown.
     *
     * @param array{0: string, 1: string, 2: string} $m
     */
    private function memberContext(string $kind, array $m, string $source, string $before): PhpInteropContext
    {
        // Bindings are looked up before the form, so the receiver itself
        // (`dt` in `(php/-> dt (get`) is not mistaken for one.
        $scope = substr($before, 0, strlen($before) - strlen($m[0]));
        $class = $this->resolveReceiver($m[1], $scope, $this->aliasExtractor->extract($source));

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
     * (`\Foo`, `Foo\Bar`, `Foo.Bar`), an inline construction or factory call
     * (see {@see formClass()}), an imported short name (`(:use Foo\Bar)` →
     * `Bar`), a bare symbol whose `:tag` / reader-tag / binding is found in the
     * source, or else a capitalised bare name as a global class. Returns ''
     * when the type is unknown.
     *
     * @param array<string, string> $aliases
     */
    private function resolveReceiverClass(string $receiver, string $source, array $aliases): string
    {
        $receiver = trim($receiver);

        if (str_starts_with($receiver, '(')) {
            return $this->formClass($receiver, $aliases);
        }

        // Class literal kept separate from the bare-symbol branch below so a
        // single unqualified name (e.g. `Widget`) is NOT treated as a literal
        // and can instead resolve through the import-alias table.
        // Multi-segment literal: \Foo\Bar, Foo\Bar or Foo.Bar.
        if (preg_match('/^\\\\?([A-Za-z_]\w*(?:[\\\\.][A-Za-z_]\w*)+)$/', $receiver, $m) === 1) {
            return str_replace('.', '\\', $m[1]);
        }

        // Single segment with a leading backslash: \Foo.
        if (preg_match('/^\\\\([A-Za-z_]\w*)$/', $receiver, $m) === 1) {
            return $m[1];
        }

        // Bare symbol: an imported short name first, then a typed local
        // binding, then a capitalised name as a global class (`DateTimeImmutable`
        // needs no `:use`).
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_\-]*$/', $receiver) === 1) {
            $class = $aliases[$receiver] ?? $this->resolveSymbolTag($receiver, $source, $aliases);

            return $class === '' && preg_match('/^[A-Z]\w*$/', $receiver) === 1 ? $receiver : $class;
        }

        return '';
    }

    /**
     * The class a form evaluates to, read from its head: a construction
     * (`(new Foo`, `(Foo.`, and `(php/new \Foo` from macro output) or a static
     * factory call (`(Foo/make`, `(php/:: Foo make`) typed by the method's
     * reflected return type. Returns '' for any other form.
     *
     * @param array<string, string> $aliases
     */
    private function formClass(string $form, array $aliases): string
    {
        $class = '\\\\?[A-Za-z_][A-Za-z0-9_\\\\.]*';

        if (preg_match('/^\(\s*(?:php\/)?new\s+(' . $class . ')/', $form, $m) === 1) {
            return $this->mapAlias($m[1], $aliases);
        }

        if (preg_match('/^\(\s*(' . $class . ')\.(?:[\s)]|$)/', $form, $m) === 1
            && $this->isClassReference($m[1])
        ) {
            return $this->mapAlias($m[1], $aliases);
        }

        if (preg_match('/^\(\s*php\/::\s+(' . $class . ')\s+\(?([A-Za-z_]\w*)/', $form, $m) === 1
            || (preg_match('/^\(\s*(' . $class . ')\/([A-Za-z_]\w*)/', $form, $m) === 1 && $this->isClassReference($m[1]))
        ) {
            return $this->reflector->methodReturnType($this->mapAlias($m[1], $aliases), $m[2]);
        }

        return '';
    }

    /**
     * Searches the source before the cursor for the type of a local binding
     * named `$symbol`:
     *
     * - a `^{:tag \Type}` map or `^\Type` reader tag,
     * - a `[symbol (new Type ...)]` or `[symbol (Type. ...)]` binding,
     * - a `[symbol (Type/make ...)]` factory binding (the static method's
     *   reflected return type),
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

        $sym = '(?<![\w\-])(?<sym>' . preg_quote($symbol, '/') . ')(?![\w\-])';

        // ^{:tag \Type} symbol  /  ^{:tag Type} symbol
        $m = $this->bindingInScope('/\^\{[^}]*:tag\s+\\\\?(?<type>[A-Za-z0-9_\\\\.]+)[^}]*\}\s+' . $sym . '/', $source);
        if ($m !== null) {
            return $this->mapAlias($m['type'], $aliases);
        }

        // ^\Type symbol  /  ^Type symbol
        $m = $this->bindingInScope('/\^\\\\?(?<type>[A-Za-z_][A-Za-z0-9_\\\\.]*)\s+' . $sym . '/', $source);
        if ($m !== null) {
            return $this->mapAlias($m['type'], $aliases);
        }

        // [symbol (new Type ...)] / [symbol (Type/make ...)]
        $m = $this->bindingInScope('/' . $sym . '\s+(?<form>\([^()]*)/', $source);
        if ($m !== null) {
            $class = $this->formClass($m['form'], $aliases);
            if ($class !== '') {
                return $class;
            }
        }

        // [symbol other-symbol]  indirect binding → follow the alias.
        $m = $this->bindingInScope('/' . $sym . '\s+(?<other>[A-Za-z_][A-Za-z0-9_\-]*)\b/', $source);
        if ($m !== null) {
            return $this->resolveSymbolTag($m['other'], $source, $aliases, [...$seen, $symbol]);
        }

        return '';
    }

    /**
     * The nearest match of `$pattern` whose `sym` group sits in a form that is
     * still open at the end of `$source` (the cursor), so a same-name binding
     * in a closed sibling scope never types the receiver. Null when none is.
     *
     * @return array<int|string, string>|null
     */
    private function bindingInScope(string $pattern, string $source): ?array
    {
        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        $openAtCursor = CursorText::openParenPositions($source);
        foreach (array_reverse($matches) as $match) {
            $enclosing = CursorText::openParenPositions(substr($source, 0, $match['sym'][1]));
            if ($enclosing === [] || in_array(array_last($enclosing), $openAtCursor, true)) {
                return array_map(static fn(array $group): string => $group[0], $match);
            }
        }

        return null;
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
