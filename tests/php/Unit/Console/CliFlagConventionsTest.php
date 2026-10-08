<?php

declare(strict_types=1);

namespace PhelTest\Unit\Console;

use Phel\Api\Infrastructure\Command\DocCommand;
use Phel\Lint\Infrastructure\Command\LintCommand;
use Phel\Mutate\Infrastructure\Command\MutateCommand;
use Phel\Profile\Infrastructure\Command\ProfileCommand;
use Phel\Run\Infrastructure\Command\ExplainCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

/**
 * Guards the short-alias conventions documented in
 * docs/internals/cli-flag-conventions.md: --format=-f, --output=-o, --sort=-s,
 * and `text` as the default format.
 */
final class CliFlagConventionsTest extends TestCase
{
    /**
     * @return iterable<string, array{Command, string, string}>
     */
    public static function provider(): iterable
    {
        yield 'doc --format' => [new DocCommand(), 'format', 'f'];
        yield 'lint --format' => [new LintCommand(), 'format', 'f'];
        yield 'profile --format' => [new ProfileCommand(), 'format', 'f'];
        yield 'profile --output' => [new ProfileCommand(), 'output', 'o'];
        yield 'profile --sort' => [new ProfileCommand(), 'sort', 's'];
        yield 'mutate --format' => [new MutateCommand(), 'format', 'f'];
        yield 'mutate --output' => [new MutateCommand(), 'output', 'o'];
    }

    /**
     * @return iterable<string, array{Command}>
     */
    public static function provideFormatCommands(): iterable
    {
        yield 'doc' => [new DocCommand()];
        yield 'explain' => [new ExplainCommand()];
        yield 'lint' => [new LintCommand()];
        yield 'profile' => [new ProfileCommand()];
    }

    #[DataProvider('provider')]
    public function test_option_has_expected_short_alias(Command $command, string $option, string $short): void
    {
        self::assertSame(
            $short,
            $command->getDefinition()->getOption($option)->getShortcut(),
        );
    }

    #[DataProvider('provideFormatCommands')]
    public function test_the_default_format_is_text(Command $command): void
    {
        self::assertSame('text', $command->getDefinition()->getOption('format')->getDefault());
    }
}
