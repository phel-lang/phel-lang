<?php

declare(strict_types=1);

namespace PhelTest\Integration;

use Gacela\Framework\Gacela;
use Phel\Api\ApiFacade;
use Phel\Build\BuildFacade;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Console\ConsoleFacade;
use Phel\Filesystem\FilesystemFacade;
use Phel\Formatter\FormatterFacade;
use Phel\Interop\InteropFacade;
use Phel\Run\RunFacade;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class ProvidesAttributeTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
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
}
