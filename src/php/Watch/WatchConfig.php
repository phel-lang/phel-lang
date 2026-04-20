<?php

declare(strict_types=1);

namespace Phel\Watch;

use Gacela\Framework\AbstractConfig;

final class WatchConfig extends AbstractConfig
{
    private const int DEFAULT_POLL_INTERVAL_MS = 500;

    private const int DEFAULT_DEBOUNCE_MS = 100;

    private const string BACKEND_AUTO = 'auto';

    public static function defaultPollIntervalMs(): int
    {
        return self::DEFAULT_POLL_INTERVAL_MS;
    }

    public static function defaultDebounceMs(): int
    {
        return self::DEFAULT_DEBOUNCE_MS;
    }

    public static function defaultBackend(): string
    {
        return self::BACKEND_AUTO;
    }
}
