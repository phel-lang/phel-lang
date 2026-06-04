<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Agent;

use function array_keys;

final class AgentPlatformRegistry
{
    /** @var array<string, AgentPlatform> */
    private array $platforms;

    public function __construct()
    {
        $this->platforms = [
            'claude' => new AgentPlatform('claude', 'skills/claude/phel-lang/SKILL.md', '.claude/skills/phel-lang/SKILL.md', ['.claude']),
            'cursor' => new AgentPlatform('cursor', 'skills/cursor/phel.mdc', '.cursor/rules/phel.mdc', ['.cursor']),
            'codex' => new AgentPlatform('codex', 'skills/codex/AGENTS.md', 'AGENTS.md', ['AGENTS.md', '.codex']),
            'gemini' => new AgentPlatform('gemini', 'skills/gemini/GEMINI.md', 'GEMINI.md', ['GEMINI.md', '.gemini']),
            'copilot' => new AgentPlatform('copilot', 'skills/copilot/copilot-instructions.md', '.github/copilot-instructions.md', ['.github/copilot-instructions.md']),
            'aider' => new AgentPlatform('aider', 'skills/aider/CONVENTIONS.md', 'CONVENTIONS.md', ['CONVENTIONS.md', '.aider.conf.yml']),
        ];
    }

    public function has(string $key): bool
    {
        return isset($this->platforms[$key]);
    }

    public function get(string $key): AgentPlatform
    {
        return $this->platforms[$key];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->platforms);
    }

    /**
     * @return array<string, AgentPlatform>
     */
    public function all(): array
    {
        return $this->platforms;
    }
}
