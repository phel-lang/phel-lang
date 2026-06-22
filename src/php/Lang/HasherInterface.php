<?php

declare(strict_types=1);

namespace Phel\Lang;

interface HasherInterface
{
    /**
     * @return int The hash of the given value
     */
    public function hash(mixed $value): int;

    /**
     * Order-sensitive hash of a sequence of values (vectors, lists, ...).
     *
     * @param iterable<mixed> $values
     */
    public function orderedHash(iterable $values): int;

    /**
     * Order-insensitive hash of a collection of values (sets).
     *
     * @param iterable<mixed> $values
     */
    public function unorderedHash(iterable $values): int;

    /**
     * Order-insensitive hash of key/value pairs (maps).
     *
     * @param iterable<mixed, mixed> $entries
     */
    public function unorderedKeyedHash(iterable $entries): int;
}
