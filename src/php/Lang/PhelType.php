<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\MapEntry;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Queue\PersistentQueue;
use Phel\Lang\Collections\Struct\AbstractPersistentStruct;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

use function is_array;
use function is_bool;
use function is_callable;
use function is_float;
use function is_int;
use function is_null;
use function is_object;
use function is_resource;
use function is_string;

/**
 * Names a value the way the language names it.
 *
 * `phel.core/type` is the answer a Phel user gets when they ask what a value
 * is, so it is the answer a message about that value has to give too. Anything
 * on the PHP side that words a type for a user reads it from here rather than
 * from `get_debug_type()`, which names the implementation: `null` for `nil`,
 * `bool` for `boolean`, and a concrete collection class that changes with the
 * size of the collection.
 *
 * `PhelTypeMatchesCoreTypeTest` pins this against `phel.core/type` itself, so
 * the two cannot drift.
 */
final readonly class PhelType
{
    public const string UNKNOWN = 'unknown';

    /**
     * The name without a leading colon, since a message says "got vector", not
     * "got :vector". The order matches `phel.core/type` exactly: an earlier
     * branch wins, so moving one changes what a value is called.
     */
    public static function nameOf(mixed $value): string
    {
        return match (true) {
            $value instanceof PersistentVectorInterface => 'vector',
            $value instanceof PersistentListInterface => 'list',
            $value instanceof AbstractPersistentStruct => 'struct',
            $value instanceof PersistentMapInterface => 'hash-map',
            $value instanceof MapEntry => 'map-entry',
            $value instanceof PersistentHashSetInterface => 'set',
            $value instanceof PersistentQueue => 'queue',
            $value instanceof Keyword => 'keyword',
            $value instanceof Symbol => 'symbol',
            $value instanceof Atom => 'atom',
            $value instanceof PhelVar => 'var',
            $value instanceof Ratio => 'ratio',
            $value instanceof BigInt => 'bigint',
            $value instanceof BigDecimal => 'bigdec',
            $value instanceof UUID => 'uuid',
            $value instanceof PhpClass => 'php/class',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_string($value) => 'string',
            is_null($value) => 'nil',
            is_bool($value) => 'boolean',
            is_callable($value) => 'function',
            is_array($value) => 'php/array',
            is_resource($value) => 'php/resource',
            is_object($value) => 'php/object',
            default => self::UNKNOWN,
        };
    }
}
