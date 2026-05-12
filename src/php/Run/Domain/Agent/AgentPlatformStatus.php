<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Agent;

final readonly class AgentPlatformStatus
{
    public const string NOT_INSTALLED = 'not_installed';

    public const string CURRENT = 'current';

    public const string STALE = 'stale';

    public const string UNSTAMPED = 'unstamped';

    public function __construct(
        public AgentPlatform $platform,
        public string $state,
        public ?string $installedVersion,
        public string $currentVersion,
        public string $installedPath,
    ) {}

    public function isDrift(): bool
    {
        return $this->state === self::STALE
            || $this->state === self::UNSTAMPED;
    }
}
