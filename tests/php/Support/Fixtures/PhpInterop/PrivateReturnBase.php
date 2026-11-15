<?php

declare(strict_types=1);

namespace PhelTest\Support\Fixtures\PhpInterop;

use DateTimeImmutable;

class PrivateReturnBase
{
    public function privateValues(): array
    {
        return [$this->common(), $this->label()];
    }

    private function common(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    private function label(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
