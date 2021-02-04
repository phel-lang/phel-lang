<?php

declare(strict_types=1);

namespace PhelTest\Integration\Interop;

use Phel\Interop\CallPhelTrait;

final class ExampleWrapper
{
    use CallPhelTrait;

    public function isOdd(int $number): bool
    {
        return $this->callPhel('phel\\core', 'odd?', $number);
    }
}
