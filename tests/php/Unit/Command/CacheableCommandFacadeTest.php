<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command;

use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Gacela;
use Phel\Command\CommandFacade;
use PHPUnit\Framework\TestCase;

final class CacheableCommandFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        CacheableConfig::reset();
        Gacela::bootstrap(__DIR__);
    }

    protected function tearDown(): void
    {
        CacheableConfig::reset();
    }

    public function test_get_all_phel_directories_returns_same_instance_on_repeated_calls(): void
    {
        $facade = new CommandFacade();

        $first = $facade->getAllPhelDirectories();
        $second = $facade->getAllPhelDirectories();

        self::assertSame($first, $second);
    }

    public function test_get_source_directories_is_cached(): void
    {
        $facade = new CommandFacade();

        $first = $facade->getSourceDirectories();
        $second = $facade->getSourceDirectories();

        self::assertSame($first, $second);
    }

    public function test_get_output_directory_is_cached(): void
    {
        $facade = new CommandFacade();

        $first = $facade->getOutputDirectory();
        $second = $facade->getOutputDirectory();

        self::assertSame($first, $second);
        self::assertIsString($first);
    }

    public function test_clear_method_cache_allows_fresh_resolution(): void
    {
        $facade = new CommandFacade();

        $first = $facade->getAllPhelDirectories();
        CommandFacade::clearMethodCache();
        $second = $facade->getAllPhelDirectories();

        self::assertEquals($first, $second);
    }

    public function test_clear_method_cache_for_specific_method(): void
    {
        $facade = new CommandFacade();

        $facade->getAllPhelDirectories();
        $facade->getSourceDirectories();

        CommandFacade::clearMethodCacheFor('getAllPhelDirectories');

        $source = $facade->getSourceDirectories();
        self::assertIsArray($source);
    }
}
