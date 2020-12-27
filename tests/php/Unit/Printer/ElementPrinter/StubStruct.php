<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Lang\Struct;

final class StubStruct extends Struct
{
    private array $allowedKeys;

    public function __construct(array $allowedKeys)
    {
        $this->allowedKeys = $allowedKeys;
    }

    public function getAllowedKeys(): array
    {
        return $this->allowedKeys;
    }
}
