<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Agent;

final readonly class AgentPlatform
{
    public function __construct(
        public string $key,
        public string $source,
        public string $target,
    ) {}
}
