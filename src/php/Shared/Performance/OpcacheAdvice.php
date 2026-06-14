<?php

declare(strict_types=1);

namespace Phel\Shared\Performance;

/**
 * Result of evaluating the OPcache configuration for CLI usage.
 */
final readonly class OpcacheAdvice
{
    /**
     * @param list<string> $messages
     */
    public function __construct(
        public bool $optimal,
        public array $messages,
    ) {}
}
