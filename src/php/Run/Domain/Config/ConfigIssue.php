<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Config;

/**
 * A single finding about the effective Phel configuration, surfaced by
 * `phel config` and `phel doctor`.
 */
final readonly class ConfigIssue
{
    public function __construct(
        public ConfigIssueLevel $level,
        public string $message,
    ) {}

    public static function error(string $message): self
    {
        return new self(ConfigIssueLevel::Error, $message);
    }

    public static function warning(string $message): self
    {
        return new self(ConfigIssueLevel::Warning, $message);
    }

    public function isError(): bool
    {
        return $this->level === ConfigIssueLevel::Error;
    }
}
