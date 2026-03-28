<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

final readonly class CompletionResultTransfer
{
    public function __construct(
        public string $candidate,
        public string $type,
    ) {}

    /**
     * @return array{candidate: string, type: string}
     */
    public function toArray(): array
    {
        return [
            'candidate' => $this->candidate,
            'type' => $this->type,
        ];
    }
}
