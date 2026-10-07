<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp;

use Phel\Lsp\LspConfig;
use Phel\Shared\VersionResolver;
use PHPUnit\Framework\TestCase;

final class LspConfigTest extends TestCase
{
    public function test_server_version_is_the_running_phel_version(): void
    {
        self::assertSame(VersionResolver::current(), LspConfig::defaultServerVersion());
    }
}
