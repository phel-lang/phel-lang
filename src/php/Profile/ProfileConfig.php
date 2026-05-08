<?php

declare(strict_types=1);

namespace Phel\Profile;

use Gacela\Framework\AbstractConfig;

final class ProfileConfig extends AbstractConfig
{
    public const string FORMAT_TABLE = 'table';

    public const string FORMAT_JSON = 'json';

    public const string FORMAT_BOTH = 'both';

    public const string SORT_SELF = 'self';

    public const string SORT_TOTAL = 'total';

    public const string SORT_CALLS = 'calls';

    public const string SORT_AVG = 'avg';

    public const int DEFAULT_TOP = 20;
}
