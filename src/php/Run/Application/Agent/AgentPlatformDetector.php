<?php

declare(strict_types=1);

namespace Phel\Run\Application\Agent;

use Phel\Run\Domain\Agent\AgentPlatformRegistry;

use function file_exists;

final readonly class AgentPlatformDetector
{
    public function __construct(private AgentPlatformRegistry $registry) {}

    /**
     * @return list<string> ordered platform keys whose signals are present in $projectRoot
     */
    public function detect(string $projectRoot): array
    {
        $detected = [];
        foreach ($this->registry->all() as $platform) {
            foreach ($platform->signals as $signal) {
                if (file_exists($projectRoot . '/' . $signal)) {
                    $detected[] = $platform->key;
                    break;
                }
            }
        }

        return $detected;
    }
}
