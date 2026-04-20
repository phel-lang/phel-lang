<?php

declare(strict_types=1);

namespace PhelTest\Integration\Watch;

use Phel\Watch\Infrastructure\Command\WatchCommand;
use PHPUnit\Framework\TestCase;

final class WatchCommandTest extends TestCase
{
    public function test_it_registers_the_watch_command_with_expected_options(): void
    {
        $command = new WatchCommand();

        self::assertSame('watch', $command->getName());
        self::assertStringContainsString('Watch Phel files', $command->getDescription());

        $definition = $command->getDefinition();
        self::assertTrue($definition->hasOption('backend'));
        self::assertTrue($definition->hasOption('poll'));
        self::assertTrue($definition->hasOption('debounce'));
        self::assertTrue($definition->hasArgument('paths'));
    }
}
