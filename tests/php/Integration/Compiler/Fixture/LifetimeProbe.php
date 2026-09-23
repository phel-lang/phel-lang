<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Fixture;

use WeakReference;

use function array_filter;
use function count;

/**
 * Counts the objects still alive, so a caller can tell when PHP released
 * the last reference to one: its own instances, and any object handed to
 * {@see self::watch()}.
 */
final class LifetimeProbe
{
    private static int $instances = 0;

    /** @var list<WeakReference<object>> */
    private static array $watched = [];

    public function __construct()
    {
        ++self::$instances;
    }

    public function __destruct()
    {
        --self::$instances;
    }

    public static function watch(object $object): void
    {
        self::$watched[] = WeakReference::create($object);
    }

    public static function alive(): int
    {
        return self::$instances + count(array_filter(
            self::$watched,
            static fn(WeakReference $ref): bool => $ref->get() !== null,
        ));
    }
}
