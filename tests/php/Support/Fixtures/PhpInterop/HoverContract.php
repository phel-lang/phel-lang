<?php

declare(strict_types=1);

namespace PhelTest\Support\Fixtures\PhpInterop;

interface HoverContract
{
    public function increment(int $by): int;
}
