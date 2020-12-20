<?php

declare(strict_types=1);

namespace Phel\Printer;

final class ObjectPrinter
{
    public function print(object $form): string
    {
        return '<PHP-Object(' . get_class($form) . ')>';
    }
}
