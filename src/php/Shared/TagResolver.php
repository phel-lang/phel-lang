<?php

declare(strict_types=1);

namespace Phel\Shared;

use Closure;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

use function is_string;
use function ltrim;
use function preg_replace_callback;
use function str_contains;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Resolves a Phel `:tag` metadata value into a scalar PHP type string.
 *
 * A `:tag` symbol resolves to its name; a non-empty string passes through
 * verbatim (`?int`, `self`, `int|null`); anything else, including a composite
 * list/vector tag (only the attribute/type emitter renders those into
 * unions/intersections), yields `null`. An empty result means "no tag".
 *
 * Two spellings save writing a class name out. `map`, `vector`, `set` and
 * `list` name the persistent collection interfaces ({@see COLLECTION_ALIASES}),
 * and a bare name the current namespace imported with `:use` resolves through
 * that table ({@see setUseAliasResolver()}), so `(:use Phel.Lang.Symbol)` lets
 * a param read `^Symbol s`. Both keep a `?` prefix and apply per member of a
 * union or intersection. A dotted or backslashed name is taken as written.
 */
final class TagResolver
{
    /**
     * Short tags for the collection interfaces. They resolve to the rooted
     * class the call-site specialisations compare against, so `^map m` gets
     * the same `->find` lowering as the full interface name.
     */
    public const array COLLECTION_ALIASES = [
        'map' => PersistentMapInterface::class,
        'vector' => PersistentVectorInterface::class,
        'set' => PersistentHashSetInterface::class,
        'list' => PersistentListInterface::class,
    ];

    /** @var (Closure(string): ?string)|null */
    private static ?Closure $useAliasResolver = null;

    /**
     * Install the lookup that answers "which class did the current namespace
     * import under this bare name". The analyser's global environment sets
     * it from its `:use` table; `null` removes it. An explicit import wins
     * over a collection alias of the same spelling.
     *
     * @param (Closure(string): ?string)|null $resolver
     */
    public static function setUseAliasResolver(?Closure $resolver): void
    {
        self::$useAliasResolver = $resolver;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public static function fromMeta(?PersistentMapInterface $meta): ?string
    {
        if (!$meta instanceof PersistentMapInterface) {
            return null;
        }

        return self::normalizeScalar($meta->find(Keyword::create('tag')));
    }

    public static function normalizeScalar(mixed $tag): ?string
    {
        if ($tag instanceof Symbol) {
            $tag = $tag->getName();
        }

        if (!is_string($tag) || $tag === '') {
            return null;
        }

        return self::rootClassReferences(self::expandAliases($tag));
    }

    /**
     * Replace every alias member of `$tag` (a bare `map`, or a name the
     * namespace imported) with its rooted class, leaving scalar types and
     * anything already qualified alone.
     */
    private static function expandAliases(string $tag): string
    {
        return preg_replace_callback(
            '/[^|&]+/',
            static fn(array $matches): string => self::expandAlias($matches[0]),
            $tag,
        ) ?? $tag;
    }

    private static function expandAlias(string $part): string
    {
        $trimmed = trim($part);
        $nullable = str_starts_with($trimmed, '?');
        $name = $nullable ? substr($trimmed, 1) : $trimmed;
        if ($name === '' || str_contains($name, '.') || str_contains($name, '\\')) {
            return $part;
        }

        $resolved = self::$useAliasResolver instanceof Closure
            ? (self::$useAliasResolver)($name)
            : null;
        $resolved ??= self::COLLECTION_ALIASES[$name] ?? null;
        if ($resolved === null) {
            return $part;
        }

        return ($nullable ? '?' : '') . '\\' . ltrim($resolved, '\\');
    }

    /**
     * A class tag may be written with either separator, `.` being the namespace
     * separator everywhere else in the language. PHP knows only `\`, and the
     * tag reaches the generated signature verbatim, so a dotted name used to
     * emit `function f(): Phel.Lang.Symbol` and fail to parse (#2924).
     *
     * No scalar type contains a dot, which is what makes the presence of one
     * the whole test. The result is rooted, because a generated file declares
     * its own `namespace` and an unrooted `Phel\Lang\Symbol` would resolve
     * against it.
     */
    private static function rootClassReferences(string $tag): string
    {
        if (!str_contains($tag, '.')) {
            return $tag;
        }

        // A union or intersection arrives as one string (`My.Type|null`), so
        // each member is rooted on its own.
        return preg_replace_callback(
            '/[^|&]+/',
            static fn(array $matches): string => self::rootClassReference($matches[0]),
            $tag,
        ) ?? $tag;
    }

    private static function rootClassReference(string $part): string
    {
        $trimmed = trim($part);
        if (!str_contains($trimmed, '.') || str_contains($trimmed, '\\')) {
            return $part;
        }

        $nullable = str_starts_with($trimmed, '?');
        $name = $nullable ? substr($trimmed, 1) : $trimmed;

        // Plain replacement rather than `Munge`: a PHP class name cannot carry
        // a character the identifier mapping would rewrite, and the tag names
        // a host class rather than a Phel namespace.
        return ($nullable ? '?' : '') . '\\' . str_replace('.', '\\', $name);
    }
}
