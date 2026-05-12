<?php

declare(strict_types=1);

namespace Phel\Run\Application\Agent;

use Phel\Run\Domain\Agent\AgentPlatformRegistry;

use function file_exists;

final readonly class AgentPlatformDetector
{
    private const array SIGNALS = [
        'claude' => ['.claude'],
        'cursor' => ['.cursor'],
        'codex' => ['AGENTS.md', '.codex'],
        'gemini' => ['GEMINI.md', '.gemini'],
        'copilot' => ['.github/copilot-instructions.md'],
        'aider' => ['CONVENTIONS.md', '.aider.conf.yml'],
    ];

    public function __construct(private AgentPlatformRegistry $registry) {}

    /**
     * @return list<string> ordered platform keys whose signals are present in $projectRoot
     */
    public function detect(string $projectRoot): array
    {
        $detected = [];
        foreach ($this->registry->keys() as $key) {
            foreach (self::SIGNALS[$key] ?? [] as $signal) {
                if (file_exists($projectRoot . '/' . $signal)) {
                    $detected[] = $key;
                    break;
                }
            }
        }

        return $detected;
    }
}
