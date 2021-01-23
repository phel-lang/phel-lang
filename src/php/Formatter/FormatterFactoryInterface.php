<?php

declare(strict_types=1);

namespace Phel\Formatter;

interface FormatterFactoryInterface
{
    public function createFormatter(): FormatterInterface;
}
