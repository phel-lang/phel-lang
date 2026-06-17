<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application\Fixtures;

interface HoverContract
{
    public function increment(int $by): int;
}
