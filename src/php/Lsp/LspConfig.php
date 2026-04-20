<?php

declare(strict_types=1);

namespace Phel\Lsp;

use Gacela\Framework\AbstractConfig;

final class LspConfig extends AbstractConfig
{
    private const int DEFAULT_DIAGNOSTIC_DEBOUNCE_MS = 200;

    private const string DEFAULT_SERVER_NAME = 'phel-lsp';

    private const string DEFAULT_SERVER_VERSION = '0.1.0';

    public static function defaultDiagnosticDebounceMs(): int
    {
        return self::DEFAULT_DIAGNOSTIC_DEBOUNCE_MS;
    }

    public static function defaultServerName(): string
    {
        return self::DEFAULT_SERVER_NAME;
    }

    public static function defaultServerVersion(): string
    {
        return self::DEFAULT_SERVER_VERSION;
    }
}
