<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

/**
 * @internal
 */
enum RepairEditKind: string
{
    case Insert = 'insert';
    case Replace = 'replace';
    case Delete = 'delete';
}
