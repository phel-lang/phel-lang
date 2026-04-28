<?php

declare(strict_types=1);

namespace Phel\Nrepl;

use Gacela\Framework\AbstractConfig;

final class NreplConfig extends AbstractConfig
{
    private const int DEFAULT_PORT = 7888;

    private const string DEFAULT_HOST = '127.0.0.1';

    public static function defaultPort(): int
    {
        return self::DEFAULT_PORT;
    }

    public static function defaultHost(): string
    {
        return self::DEFAULT_HOST;
    }
}
