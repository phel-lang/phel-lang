<?php

declare(strict_types=1);

namespace PhelTest\Integration\Interop;

use Phel\Interop\PhelCallerTrait;

final class ExampleWrapper
{
    use PhelCallerTrait;

    public function isOdd(int $number): bool
    {
        return self::callPhel('phel\\core', 'odd?', $number);
    }
}
