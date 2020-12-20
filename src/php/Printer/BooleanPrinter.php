<?php

declare(strict_types=1);

namespace Phel\Printer;

final class BooleanPrinter
{
    public function print(bool $form): string
    {
        return $form === true
            ? 'true'
            : 'false';
    }
}
