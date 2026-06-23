<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Config;

/**
 * Severity of a configuration diagnostic. Errors mean the config is wrong and
 * something will break; warnings flag likely mistakes that still let Phel run.
 */
enum ConfigIssueLevel: string
{
    case Error = 'error';
    case Warning = 'warning';

    public function label(): string
    {
        return match ($this) {
            self::Error => 'ERROR',
            self::Warning => 'WARNING',
        };
    }
}
