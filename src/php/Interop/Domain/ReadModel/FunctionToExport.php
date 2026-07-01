<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\ReadModel;

use Phel\Lang\FnInterface;

final readonly class FunctionToExport
{
    /**
     * @param mixed       $attributes the `:php/attr` metadata spec (a vector of
     *                                attribute specs), or null when none is present
     * @param string|null $returnTag  the return-type `:tag` string from the definition
     *                                metadata, or null when the fn is untagged
     */
    public function __construct(
        private FnInterface $fn,
        private mixed $attributes = null,
        private ?string $returnTag = null,
    ) {}

    public function fn(): FnInterface
    {
        return $this->fn;
    }

    public function attributes(): mixed
    {
        return $this->attributes;
    }

    public function returnTag(): ?string
    {
        return $this->returnTag;
    }
}
