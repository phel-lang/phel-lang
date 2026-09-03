<?php

declare(strict_types=1);

namespace Phel\Shared;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;

use function is_string;

/**
 * Reads the `:deprecated` entry of a definition's metadata.
 *
 * A non-empty string is the reason; a bare `^:deprecated` (the value `true`)
 * degrades to the literal `'deprecated'`; anything else, missing metadata
 * included, yields `''` for "not deprecated".
 *
 * Shared so Api's symbol extractor (which feeds `Definition::$deprecated`) and
 * Lint's `discouraged-var` rule cannot drift on what counts as deprecated.
 */
final class DeprecationResolver
{
    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public static function reasonFromMeta(?PersistentMapInterface $meta): string
    {
        if (!$meta instanceof PersistentMapInterface) {
            return '';
        }

        $value = $meta->find(Keyword::create('deprecated'));
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $value === true ? 'deprecated' : '';
    }
}
