<?php

declare(strict_types=1);

namespace PhelTest\Integration\Interop;

use Phel\Interop\PhelCallerTrait;

final class ExampleWrapper
{
    use PhelCallerTrait;

    public function isOdd(int $number): bool
    {
        return $this->callPhel('phel\\core', 'odd?', $number);
    }

    public function printStr(string ...$strings): string
    {
        return $this->callPhel('phel\\core', 'print-str', ...$strings);
    }
}
