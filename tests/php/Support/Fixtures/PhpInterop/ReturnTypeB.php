<?php

declare(strict_types=1);

namespace PhelTest\Support\Fixtures\PhpInterop;

interface ReturnTypeB
{
    public string $Label { get; }

    public function next(): ?self;

    public function generic(): object;

    public function common(string $value): self;

    public function onlyB(): void;
}
