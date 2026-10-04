<?php

declare(strict_types=1);

namespace PhelTest\Unit\Console\Infrastructure\Command;

use Phel\Console\Infrastructure\Command\FrameworkCommands;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;

final class FrameworkCommandsTest extends TestCase
{
    /**
     * @return iterable<string, array{LazyCommand}>
     */
    public static function registeredCommands(): iterable
    {
        foreach (new FrameworkCommands()->lazyCommands() as $command) {
            yield (string) $command->getName() => [$command];
        }
    }

    #[DataProvider('registeredCommands')]
    public function test_help_and_description_name_the_phel_binary_only(LazyCommand $lazy): void
    {
        $command = $lazy->getCommand();
        $text = $command->getDescription() . "\n" . $command->getHelp();

        self::assertStringNotContainsString('bin/gacela', $text);
        self::assertStringNotContainsString('gacela.php', $text);
    }

    public function test_usage_examples_keep_their_arguments_under_phel(): void
    {
        foreach (new FrameworkCommands()->lazyCommands() as $lazy) {
            if ($lazy->getName() === 'debug:container') {
                self::assertStringContainsString('phel debug:container --stats', $lazy->getCommand()->getHelp());

                return;
            }
        }

        self::fail('debug:container is not registered');
    }

    public function test_cache_warm_help_names_the_phel_binary(): void
    {
        $help = $this->cacheWarmCommand()->getHelp();

        self::assertStringContainsString('phel cache:warm --clear --attributes', $help);
        self::assertStringContainsString('phel cache:clear', $help);
        self::assertStringNotContainsString('bin/gacela', $help);
        self::assertStringNotContainsString('gacela.php', $help);
        self::assertStringNotContainsString('clear-cache', $help);
    }

    public function test_cache_warm_keeps_its_options(): void
    {
        $definition = $this->cacheWarmCommand()->getDefinition();

        self::assertSame('c', $definition->getOption('clear')->getShortcut());
        self::assertSame('a', $definition->getOption('attributes')->getShortcut());
    }

    private function cacheWarmCommand(): Command
    {
        foreach (new FrameworkCommands()->lazyCommands() as $lazy) {
            if ($lazy->getName() === 'cache:warm') {
                self::assertInstanceOf(LazyCommand::class, $lazy);

                return $lazy->getCommand();
            }
        }

        self::fail('cache:warm is not registered');
    }
}
