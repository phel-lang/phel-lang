<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Infrastructure\Command;

use Phel\Run\Infrastructure\Command\ShellCompletionTip;
use PHPUnit\Framework\TestCase;

use function implode;

final class ShellCompletionTipTest extends TestCase
{
    public function test_tailors_command_to_a_recognised_shell(): void
    {
        $text = implode("\n", ShellCompletionTip::lines('/usr/bin/zsh'));

        self::assertStringContainsString('phel completion zsh', $text);
        self::assertStringNotContainsString('bash|zsh|fish', $text);
    }

    public function test_falls_back_to_generic_command_for_unknown_shell(): void
    {
        $text = implode("\n", ShellCompletionTip::lines('/usr/bin/nu'));

        self::assertStringContainsString('phel completion bash|zsh|fish', $text);
    }

    public function test_falls_back_when_shell_env_is_absent(): void
    {
        self::assertStringContainsString(
            'phel completion bash|zsh|fish',
            implode("\n", ShellCompletionTip::lines(false)),
        );
        self::assertStringContainsString(
            'phel completion bash|zsh|fish',
            implode("\n", ShellCompletionTip::lines(null)),
        );
        self::assertStringContainsString(
            'phel completion bash|zsh|fish',
            implode("\n", ShellCompletionTip::lines('')),
        );
    }

    public function test_always_mentions_completion_and_readme(): void
    {
        $text = implode("\n", ShellCompletionTip::lines('/bin/bash'));

        self::assertStringContainsString('shell completion', $text);
        self::assertStringContainsString('README', $text);
    }
}
