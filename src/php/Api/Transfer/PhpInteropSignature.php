<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

/**
 * A reflected PHP callable signature: the rendered `name(params): return` label,
 * the per-parameter label substrings (so an editor can highlight the active
 * argument), and the cleaned phpdoc, if any.
 */
final readonly class PhpInteropSignature
{
    /**
     * @param list<string> $parameters parameter label substrings, e.g. `string $name`
     */
    public function __construct(
        public string $label,
        public array $parameters,
        public string $documentation = '',
    ) {}
}
