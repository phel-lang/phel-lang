<?php

declare(strict_types=1);

namespace PhelTest\Fixtures\Enum;

/**
 * A native backed enum so the Phel enum<->keyword bridge tests can round-trip
 * an external PHP enum.
 */
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
