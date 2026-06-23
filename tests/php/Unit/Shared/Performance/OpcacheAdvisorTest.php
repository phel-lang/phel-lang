<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Performance;

use Phel\Shared\Performance\OpcacheAdvisor;
use PHPUnit\Framework\TestCase;

final class OpcacheAdvisorTest extends TestCase
{
    public function test_opcache_not_loaded_is_not_optimal(): void
    {
        $advice = new OpcacheAdvisor()->advise(
            opcacheLoaded: false,
            enableCli: false,
            fileCacheConfigured: false,
        );

        self::assertFalse($advice->optimal);
        self::assertCount(1, $advice->messages);
        self::assertStringContainsStringIgnoringCase('opcache', $advice->messages[0]);
    }

    public function test_loaded_but_disabled_on_cli_warns_about_enable_cli(): void
    {
        $advice = new OpcacheAdvisor()->advise(
            opcacheLoaded: true,
            enableCli: false,
            fileCacheConfigured: false,
        );

        self::assertFalse($advice->optimal);
        self::assertStringContainsString('opcache.enable_cli', implode("\n", $advice->messages));
        self::assertStringContainsString('opcache.file_cache', implode("\n", $advice->messages));
    }

    public function test_enabled_without_file_cache_warns_only_about_file_cache(): void
    {
        $advice = new OpcacheAdvisor()->advise(
            opcacheLoaded: true,
            enableCli: true,
            fileCacheConfigured: false,
        );

        self::assertFalse($advice->optimal);
        self::assertCount(1, $advice->messages);
        self::assertStringContainsString('opcache.file_cache', $advice->messages[0]);
    }

    public function test_fully_configured_is_optimal(): void
    {
        $advice = new OpcacheAdvisor()->advise(
            opcacheLoaded: true,
            enableCli: true,
            fileCacheConfigured: true,
        );

        self::assertTrue($advice->optimal);
        self::assertCount(1, $advice->messages);
    }

    public function test_file_cache_advice_warns_about_absolute_existing_path(): void
    {
        $advice = new OpcacheAdvisor()->advise(
            opcacheLoaded: true,
            enableCli: true,
            fileCacheConfigured: false,
        );

        // The fix must not trade one startup failure for another: PHP aborts
        // when file_cache is missing or relative, so the caveat is explicit.
        self::assertStringContainsStringIgnoringCase('absolute', $advice->messages[0]);
        self::assertStringContainsStringIgnoringCase('aborts', $advice->messages[0]);
    }

    public function test_ini_template_pointer_is_appended_when_not_optimal(): void
    {
        $advice = new OpcacheAdvisor()->advise(
            opcacheLoaded: true,
            enableCli: false,
            fileCacheConfigured: false,
            iniTemplatePath: '/pkg/phel.ini',
        );

        self::assertFalse($advice->optimal);
        self::assertStringContainsString('/pkg/phel.ini', implode("\n", $advice->messages));
    }

    public function test_ini_template_pointer_is_omitted_when_optimal(): void
    {
        $advice = new OpcacheAdvisor()->advise(
            opcacheLoaded: true,
            enableCli: true,
            fileCacheConfigured: true,
            iniTemplatePath: '/pkg/phel.ini',
        );

        self::assertTrue($advice->optimal);
        self::assertStringNotContainsString('/pkg/phel.ini', implode("\n", $advice->messages));
    }
}
