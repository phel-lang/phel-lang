<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

final class NormalizedPhelFunction
{
    public function __construct(
        private string $fnName,
        private string $doc,
        private string $fnSignature,
        private string $description,
    ) {
    }
    /**
     * @param array{fnName:string, doc:string, fnSignature:string, desc:string} $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['fnName'] ?? '',
            $array['doc'] ?? '',
            $array['fnSignature'] ?? '',
            $array['desc'] ?? '',
        );
    }

    public function fnName(): string
    {
        return $this->fnName;
    }

    public function doc(): string
    {
        return $this->doc;
    }

    public function fnSignature(): string
    {
        return $this->fnSignature;
    }

    public function description(): string
    {
        return $this->description;
    }
}
