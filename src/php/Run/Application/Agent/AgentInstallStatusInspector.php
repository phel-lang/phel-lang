<?php

declare(strict_types=1);

namespace Phel\Run\Application\Agent;

use Phel\Run\Domain\Agent\AgentPlatform;
use Phel\Run\Domain\Agent\AgentPlatformRegistry;
use Phel\Run\Domain\Agent\AgentPlatformStatus;

use function is_file;

final readonly class AgentInstallStatusInspector
{
    public function __construct(
        private AgentPlatformRegistry $registry,
        private AgentVersionStamper $stamper,
    ) {}

    /**
     * @return list<AgentPlatformStatus>
     */
    public function inspect(string $projectRoot): array
    {
        $current = $this->stamper->currentVersion() ?? 'unknown';
        $statuses = [];

        foreach ($this->registry->all() as $platform) {
            $statuses[] = $this->inspectPlatform($platform, $projectRoot, $current);
        }

        return $statuses;
    }

    private function inspectPlatform(AgentPlatform $platform, string $projectRoot, string $current): AgentPlatformStatus
    {
        $path = $projectRoot . '/' . $platform->target;

        if (!is_file($path)) {
            return new AgentPlatformStatus($platform, AgentPlatformStatus::NOT_INSTALLED, null, $current, $path);
        }

        $installed = $this->stamper->installedVersion($path);
        if ($installed === null) {
            return new AgentPlatformStatus($platform, AgentPlatformStatus::UNSTAMPED, null, $current, $path);
        }

        $state = $installed === $current
            ? AgentPlatformStatus::CURRENT
            : AgentPlatformStatus::STALE;

        return new AgentPlatformStatus($platform, $state, $installed, $current, $path);
    }
}
