<?php

declare(strict_types=1);

namespace PhelTest\Fixtures\Interop;

use LogicException;

/**
 * Plain public-property DTO used by the Phel-level `hydrate`/`bean` tests.
 *
 * The constructor throws so the tests prove `hydrate` rebuilds the object
 * without invoking it (the ORM/serializer pattern).
 */
final class HydrationTarget
{
    public int $id = 0;

    public string $name = '';

    public function __construct()
    {
        throw new LogicException('constructor must not run during hydration');
    }
}
