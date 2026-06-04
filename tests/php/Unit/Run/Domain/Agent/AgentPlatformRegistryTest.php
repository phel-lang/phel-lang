<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Agent;

use Phel\Run\Domain\Agent\AgentPlatformRegistry;
use PHPUnit\Framework\TestCase;

final class AgentPlatformRegistryTest extends TestCase
{
    public function test_keys_lists_every_supported_platform(): void
    {
        self::assertSame(
            ['claude', 'cursor', 'codex', 'gemini', 'copilot', 'aider'],
            new AgentPlatformRegistry()->keys(),
        );
    }

    public function test_has_distinguishes_known_from_unknown(): void
    {
        $registry = new AgentPlatformRegistry();

        self::assertTrue($registry->has('claude'));
        self::assertFalse($registry->has('borg'));
    }

    public function test_get_returns_platform_with_source_target_and_signals(): void
    {
        $claude = new AgentPlatformRegistry()->get('claude');

        self::assertSame('claude', $claude->key);
        self::assertSame('skills/claude/phel-lang/SKILL.md', $claude->source);
        self::assertSame('.claude/skills/phel-lang/SKILL.md', $claude->target);
        self::assertContains('.claude', $claude->signals);
    }

    public function test_all_is_keyed_by_platform_key(): void
    {
        $all = new AgentPlatformRegistry()->all();

        self::assertSame(['claude', 'cursor', 'codex', 'gemini', 'copilot', 'aider'], array_keys($all));
        self::assertSame('aider', $all['aider']->key);
    }
}
