<?php

declare(strict_types=1);

namespace Phel\Shared\Performance;

/**
 * Outcome of {@see OpcacheReexec::decide()}: whether the CLI should replace its
 * own process image to gain a persistent OPcache file cache, and the `-d` flags
 * to apply when it does.
 */
final readonly class OpcacheReexecDecision
{
    /**
     * @param list<string> $flags
     */
    public function __construct(
        public bool $shouldReexec,
        public array $flags,
    ) {}
}
