<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain;

/**
 * @internal
 */
final readonly class MutantResult
{
    public function __construct(
        public Mutant $mutant,
        public MutantVerdict $verdict,
        public float $seconds,
        public ?string $detail = null,
    ) {}
}
