<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Agent;

final readonly class AgentPlatform
{
    /**
     * @param list<string> $signals project-root paths whose presence marks this platform as in use
     */
    public function __construct(
        public string $key,
        public string $source,
        public string $target,
        public array $signals = [],
    ) {}
}
