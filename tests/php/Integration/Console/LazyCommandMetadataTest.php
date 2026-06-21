<?php

declare(strict_types=1);

namespace PhelTest\Integration\Console;

use Phel;
use Phel\Console\ConsoleFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\LazyCommand;

use function array_keys;
use function count;
use function sort;

/**
 * Guards that the lazy command loader exposes the same name/alias/description/
 * hidden metadata that each command declares in its own configure(), so the
 * list/help output stays accurate without eagerly building every command.
 */
final class LazyCommandMetadataTest extends TestCase
{
    public function test_lazy_metadata_matches_each_constructed_command(): void
    {
        Phel::bootstrap(__DIR__);
        $loader = new ConsoleFactory()->createCommandLoader();

        $canonicalNames = [];
        foreach ($loader->getNames() as $name) {
            $lazy = $loader->get($name);
            self::assertInstanceOf(LazyCommand::class, $lazy);

            // Reading metadata off the LazyCommand must reflect the wrapped
            // command once it is finally constructed.
            $real = $lazy->getCommand();

            self::assertSame($real->getName(), $lazy->getName(), $name . ': name drift');
            self::assertSame($real->getDescription(), $lazy->getDescription(), $name . ': description drift');
            self::assertSame($real->getAliases(), $lazy->getAliases(), $name . ': alias drift');
            self::assertSame($real->isHidden(), $lazy->isHidden(), $name . ': hidden drift');

            $canonicalNames[$lazy->getName()] = true;
        }

        $names = array_keys($canonicalNames);
        sort($names);

        self::assertContains('repl', $names, 'default command must be registered');
        self::assertContains('_test-worker', $names, 'hidden worker command must be registered');
        self::assertGreaterThan(20, count($names), 'expected the full command surface to be exposed');
    }

    public function test_default_command_repl_is_resolvable_through_the_loader(): void
    {
        Phel::bootstrap(__DIR__);
        $application = new ConsoleFactory()->createConsoleBootstrap();

        self::assertTrue($application->has('repl'));
        self::assertSame('repl', $application->find('repl')->getName());
    }
}
