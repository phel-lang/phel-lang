<?php

declare(strict_types=1);

namespace PhelTest\Unit\Console\Infrastructure\Command;

use Phel\Console\Infrastructure\Command\FrameworkCommands;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;

final class FrameworkCommandsTest extends TestCase
{
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
