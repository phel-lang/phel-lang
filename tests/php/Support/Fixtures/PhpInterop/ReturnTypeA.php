<?php

declare(strict_types=1);

namespace PhelTest\Support\Fixtures\PhpInterop;

interface ReturnTypeA
{
    public string $label { get; }

    public function next(): ?self;

    public function generic(): self;

    public function common(int $value): self;

    public function onlyA(): void;
}
