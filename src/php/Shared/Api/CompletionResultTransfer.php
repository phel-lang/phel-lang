<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

final readonly class CompletionResultTransfer
{
    public function __construct(
        public string $candidate,
        public string $type,
    ) {}
}
