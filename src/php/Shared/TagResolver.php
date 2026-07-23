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

        return is_string($tag) && $tag !== '' ? $tag : null;
    }
}
