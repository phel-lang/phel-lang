<?php

declare(strict_types=1);

namespace PhelTest\Unit\Interop;

use Phel\Interop\CallPhelTrait;

class ExampleWrapper
{
    use CallPhelTrait;

    public function isOdd(int $number): bool
    {
        return $this->callPhel('phel\\core', 'odd?', $number);
    }
}
