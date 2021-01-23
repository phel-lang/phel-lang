<?php

declare(strict_types=1);

namespace Phel\Formatter;

interface FormatterInterface
{
    public function formatFile(string $filename): void;
}
