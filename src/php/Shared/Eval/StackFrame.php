<?php

declare(strict_types=1);

namespace Phel\Shared\Eval;

final readonly class StackFrame
{
    public function __construct(
        public string $file,
        public int $line,
        public ?string $class,
        public ?string $function,
    ) {}
}
