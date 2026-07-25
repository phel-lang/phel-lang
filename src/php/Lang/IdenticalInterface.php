<?php

declare(strict_types=1);

namespace Phel\Lang;

interface IdenticalInterface
{
    public function identical(mixed $other): bool;
}
