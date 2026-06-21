<?php

declare(strict_types=1);

namespace PhelTest\Unit\Console\Infrastructure\Command;

use Phel\Console\Infrastructure\Command\LazyCommandLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Exception\CommandNotFoundException;

final class LazyCommandLoaderTest extends TestCase
{
    public function test_get_constructs_only_the_requested_command(): void
    {
        $built = [];

        $loader = new LazyCommandLoader([
            $this->lazyCommand('alpha', [], static function () use (&$built): Command {
                $built[] = 'alpha';

                return new Command('alpha');
            }),
            $this->lazyCommand('beta', [], static function () use (&$built): Command {
                $built[] = 'beta';

                return new Command('beta');
            }),
        ]);

        $command = $loader->get('alpha');
        self::assertInstanceOf(LazyCommand::class, $command);
        self::assertSame('alpha', $command->getName());

        // The underlying command is only constructed when actually used; the
        // sibling command's factory is never touched.
        $command->getCommand();
        self::assertSame(['alpha'], $built, 'only the requested command factory should run');
    }

    public function test_metadata_is_available_without_constructing_the_command(): void
    {
        $built = false;

        $loader = new LazyCommandLoader([
            $this->lazyCommand('gamma', ['g'], static function () use (&$built): Command {
                $built = true;

                return new Command('gamma');
            }, 'Gamma description'),
        ]);

        $command = $loader->get('gamma');

        self::assertSame('gamma', $command->getName());
        self::assertSame(['g'], $command->getAliases());
        self::assertSame('Gamma description', $command->getDescription());
        self::assertFalse($built, 'reading metadata must not trigger the factory');
    }

    public function test_has_and_get_resolve_aliases(): void
    {
        $loader = new LazyCommandLoader([
            $this->lazyCommand('delta', ['d', 'dl'], static fn(): Command => new Command('delta')),
        ]);

        self::assertTrue($loader->has('delta'));
        self::assertTrue($loader->has('d'));
        self::assertTrue($loader->has('dl'));
        self::assertFalse($loader->has('unknown'));

        self::assertSame('delta', $loader->get('d')->getName());
        self::assertSame('delta', $loader->get('dl')->getName());
    }

    public function test_get_names_lists_canonical_names_and_aliases(): void
    {
        $loader = new LazyCommandLoader([
            $this->lazyCommand('epsilon', ['e'], static fn(): Command => new Command('epsilon')),
            $this->lazyCommand('zeta', [], static fn(): Command => new Command('zeta')),
        ]);

        self::assertSame(['epsilon', 'e', 'zeta'], $loader->getNames());
    }

    public function test_get_throws_for_unknown_command(): void
    {
        $loader = new LazyCommandLoader([]);

        $this->expectException(CommandNotFoundException::class);
        $loader->get('missing');
    }

    /**
     * @param list<string> $aliases
     */
    private function lazyCommand(string $name, array $aliases, callable $factory, string $description = ''): LazyCommand
    {
        return new LazyCommand($name, $aliases, $description, false, $factory(...));
    }
}
