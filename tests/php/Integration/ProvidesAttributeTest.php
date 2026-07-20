<?php

declare(strict_types=1);

namespace PhelTest\Integration;

use Gacela\Framework\Gacela;
use Gacela\Framework\Testing\GacelaTestCase;
use Phel\Api\ApiFacade;
use Phel\Build\BuildFacade;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Console\ConsoleFacade;
use Phel\Filesystem\FilesystemFacade;
use Phel\Formatter\FormatterFacade;
use Phel\Interop\InteropFacade;
use Phel\Run\RunFacade;

use function sprintf;

final class ProvidesAttributeTest extends GacelaTestCase
{
    protected function setUp(): void
    {
        $this->bootstrapGacela(__DIR__);
    }

    public function test_all_facades_resolve_from_container(): void
    {
        $facades = [
            ApiFacade::class,
            BuildFacade::class,
            CommandFacade::class,
            CompilerFacade::class,
            ConsoleFacade::class,
            FilesystemFacade::class,
            FormatterFacade::class,
            InteropFacade::class,
            RunFacade::class,
        ];

        foreach ($facades as $facadeClass) {
            $facade = Gacela::get($facadeClass);
            self::assertInstanceOf($facadeClass, $facade, sprintf(
                '%s should be resolvable from the Gacela container',
                $facadeClass,
            ));
            // Event-backed cross-check: the container (not a stale locator
            // cache from a previous test) actually resolved the service.
            $this->assertServiceResolved($facadeClass);
        }
    }

    public function test_command_facade_directories_resolve_through_provider(): void
    {
        $facade = new CommandFacade();

        $dirs = $facade->getAllPhelDirectories();

        self::assertIsArray($dirs);
    }

    public function test_compiler_facade_resolves_through_provider(): void
    {
        $facade = new CompilerFacade();

        $encoded = $facade->encodeNs('phel\\core');

        self::assertSame('phel\core', $encoded);
    }

    public function test_console_facade_version_resolves_through_provider(): void
    {
        $facade = new ConsoleFacade();

        $version = $facade->getVersion();

        self::assertIsString($version);
        self::assertNotEmpty($version);
    }

    public function test_run_facade_version_resolves_without_console_dependency(): void
    {
        // Run no longer depends on Console for its version; both resolve the
        // same string straight from the Shared VersionResolver.
        $runVersion = new RunFacade()->getVersion();
        $consoleVersion = new ConsoleFacade()->getVersion();

        self::assertNotEmpty($runVersion);
        self::assertSame($consoleVersion, $runVersion);
    }
}
