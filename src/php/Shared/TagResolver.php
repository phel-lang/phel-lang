<?php

declare(strict_types=1);

namespace Phel\Shared;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

use function is_string;

/**
 * Resolves a Phel `:tag` metadata value into a scalar PHP type string.
 *
 * A `:tag` symbol resolves to its name; a non-empty string passes through
 * verbatim (`?int`, `self`, `int|null`); anything else, including a composite
 * list/vector tag (only the attribute/type emitter renders those into
 * unions/intersections), yields `null`. An empty result means "no tag".
 */
final class TagResolver
{
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

        return self::rootClassReferences($tag);
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
