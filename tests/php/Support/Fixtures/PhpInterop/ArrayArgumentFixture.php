<?php

declare(strict_types=1);

namespace PhelTest\Support\Fixtures\PhpInterop;

final class ArrayArgumentFixture
{
    public function takeOptions(array $options): array
    {
        return $options;
    }
}
