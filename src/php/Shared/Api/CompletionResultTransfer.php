<?php

declare(strict_types=1);

namespace Phel\Shared\Api;

final readonly class CompletionResultTransfer
{
    public function __construct(
        public string $candidate,
        public string $type,
    ) {}
}
