<?php

declare(strict_types=1);

namespace Phel\Profile\Domain;

enum ReportFormat: string
{
    case Table = 'table';
    case Json = 'json';
    case Both = 'both';

    public function emitsTable(): bool
    {
        return $this === self::Table || $this === self::Both;
    }

    public function emitsJson(): bool
    {
        return $this === self::Json || $this === self::Both;
    }
}
