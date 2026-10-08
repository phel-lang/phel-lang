<?php

declare(strict_types=1);

namespace Phel\Profile\Domain;

/**
 * @internal
 */
enum ReportFormat: string
{
    case Text = 'text';
    case Json = 'json';
    case Both = 'both';

    private const string FORMER_TEXT_NAME = 'table';

    public static function fromOption(string $name): ?self
    {
        return self::tryFrom($name === self::FORMER_TEXT_NAME ? self::Text->value : $name);
    }

    public function emitsText(): bool
    {
        return $this === self::Text || $this === self::Both;
    }

    public function emitsJson(): bool
    {
        return $this === self::Json || $this === self::Both;
    }
}
