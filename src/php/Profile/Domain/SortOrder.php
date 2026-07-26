<?php

declare(strict_types=1);

namespace Phel\Profile\Domain;

/**
 * @internal
 */
enum SortOrder: string
{
    case SelfTime = 'self';
    case TotalTime = 'total';
    case Calls = 'calls';
    case Avg = 'avg';
}
