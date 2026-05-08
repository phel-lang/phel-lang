<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

final readonly class ReplFormattedError
{
    public function __construct(
        public string $headline,
        public ?string $hint,
        public string $trace,
        public string $fullTrace,
    ) {}
}
