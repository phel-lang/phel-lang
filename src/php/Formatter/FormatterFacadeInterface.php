<?php

declare(strict_types=1);

namespace Phel\Formatter;

interface FormatterFacadeInterface
{
    public function format(string $string, string $source = 'string'): string;
}
