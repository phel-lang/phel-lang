<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

/**
 * @internal
 */
enum RepairStrategy: string
{
    case Append = 'append';
    case Boundary = 'boundary';
    case DeleteUnexpected = 'delete-unexpected';
    case Search = 'search';
}
