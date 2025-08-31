<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader;

use Phel\Lang\Symbol;

final class GensymContext
{
    /** @var array<string, Symbol> */
    public array $symbols = [];
}
