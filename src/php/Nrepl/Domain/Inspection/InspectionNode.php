<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Inspection;

use Generator;

/**
 * @internal
 */
final class InspectionNode
{
    /** @var Generator<int, array{mixed, mixed}>|null */
    public ?Generator $entries = null;

    /** @var list<array<string, mixed>> */
    public array $children = [];

    public bool $advance = false;

    public bool $complete = false;

    public string $reason = '';

    public function __construct(public mixed $value, public readonly int $depth) {}

    public function release(): void
    {
        $this->children = [];
        try {
            $this->value = null;
        } finally {
            $this->entries = null;
        }
    }
}
