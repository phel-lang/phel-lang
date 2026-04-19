<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Repl;

use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Run\Domain\Repl\ReplPrompt;
use PHPUnit\Framework\TestCase;

final class ReplPromptTest extends TestCase
{
    protected function setUp(): void
    {
        GlobalEnvironmentSingleton::reset();
    }

    protected function tearDown(): void
    {
        GlobalEnvironmentSingleton::reset();
    }

    public function test_initial_prompt_uses_default_namespace_when_env_uninitialized(): void
    {
        $prompt = new ReplPrompt();

        self::assertSame('user:1> ', $prompt->initial(1));
    }

    public function test_continuation_prompt_format(): void
    {
        $prompt = new ReplPrompt();

        self::assertSame('....:7> ', $prompt->continuation(7));
    }

    public function test_initial_prompt_reflects_current_namespace(): void
    {
        GlobalEnvironmentSingleton::initializeNew();
        GlobalEnvironmentSingleton::getInstance()->setNs('my-app\\core');

        $prompt = new ReplPrompt();

        self::assertSame('my-app\\core:3> ', $prompt->initial(3));
    }
}
