<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Infrastructure\Command;

use InvalidArgumentException;
use Phel\Run\Domain\Test\TestCommandOptions;
use Phel\Run\Infrastructure\Command\TestCommand;
use Phel\Run\Infrastructure\Command\TestCommandOptionParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;

final class TestCommandRepeatSeedTest extends TestCase
{
    public function test_repeat_flag_appears_in_phel_hash_map(): void
    {
        $printed = $this->collectAndPrint(['--repeat' => '4']);

        self::assertStringContainsString(':repeat 4', $printed);
    }

    public function test_repeat_default_does_not_appear(): void
    {
        $printed = $this->collectAndPrint([]);

        self::assertStringNotContainsString(':repeat', $printed);
    }

    public function test_repeat_zero_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--repeat must be a positive integer');

        $this->collectAndPrint(['--repeat' => '0']);
    }

    public function test_repeat_negative_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->collectAndPrint(['--repeat' => '-2']);
    }

    public function test_repeat_non_numeric_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--repeat must be a positive integer, got abc');

        $this->collectAndPrint(['--repeat' => 'abc']);
    }

    public function test_seed_flag_appears_in_phel_hash_map(): void
    {
        $printed = $this->collectAndPrint(['--seed' => '777']);

        self::assertStringContainsString(':seed 777', $printed);
    }

    public function test_seed_default_does_not_appear(): void
    {
        $printed = $this->collectAndPrint([]);

        self::assertStringNotContainsString(':seed', $printed);
    }

    public function test_seed_non_numeric_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--seed must be an integer');

        $this->collectAndPrint(['--seed' => 'abc']);
    }

    public function test_random_order_flag_appears_in_phel_hash_map(): void
    {
        $printed = $this->collectAndPrint(['--random-order' => true]);

        self::assertStringContainsString(':random-order true', $printed);
    }

    public function test_random_order_default_does_not_appear(): void
    {
        $printed = $this->collectAndPrint([]);

        self::assertStringNotContainsString(':random-order', $printed);
    }

    public function test_combined_flags_round_trip(): void
    {
        $printed = $this->collectAndPrint([
            '--repeat' => '3',
            '--seed' => '42',
            '--random-order' => true,
        ]);

        self::assertStringContainsString(':repeat 3', $printed);
        self::assertStringContainsString(':seed 42', $printed);
        self::assertStringContainsString(':random-order true', $printed);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function collectAndPrint(array $arguments): string
    {
        $command = new TestCommand();
        $input = new ArrayInput($arguments, $command->getDefinition());

        $collected = new TestCommandOptionParser()->collectOptions($input);

        return TestCommandOptions::fromArray($collected)->asPhelHashMap();
    }
}
