<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader;

use Phel\Lang\Symbol;

final class GensymContext
{
    /** @var array<string, Symbol> */
    private array $symbols = [];

    public function getSymbolOrCreate(string $base): Symbol
    {
        return $this->symbols[$base] ??= Symbol::gen($base);
    }
}
