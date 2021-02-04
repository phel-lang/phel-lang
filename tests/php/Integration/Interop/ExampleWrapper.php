<?php

declare(strict_types=1);

namespace PhelTest\Integration\Interop;

use Phel\Interop\PhelCallableInterface;

final class ExampleWrapper
{
    private PhelCallableInterface $phelCallable;

    public function __construct(PhelCallableInterface $phelCallable)
    {
        $this->phelCallable = $phelCallable;
    }

    public function isOdd(int $number): bool
    {
        return  $this->phelCallable->callPhel('phel\\core', 'odd?', $number);
    }
}
