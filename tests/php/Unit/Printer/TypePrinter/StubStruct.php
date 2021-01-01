<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Lang\AbstractStruct;

final class StubStruct extends AbstractStruct
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
