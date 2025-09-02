<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

final readonly class PhelFunction
{
    public function __construct(
        private string $fnName,
        private string $doc,
        private string $fnSignature,
        private string $description,
        private string $groupKey = '',
        private string $url = '',
        private string $file = '',
        private int $line = 0,
    ) {
    }

    /**
     * @param array{
     *     fnName?: string,
     *     doc?: string,
     *     fnSignature?: string,
     *     desc?: string,
     *     groupKey?: string,
     *     url?: string,
     *     file?: string,
     *     line?: int,
     * } $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['fnName'] ?? '',
            $array['doc'] ?? '',
            $array['fnSignature'] ?? '',
            $array['desc'] ?? '',
            $array['groupKey'] ?? '',
            $array['url'] ?? '',
            $array['file'] ?? '',
            $array['line'] ?? 0,
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

    public function groupKey(): string
    {
        return $this->groupKey;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function file(): string
    {
        return $this->file;
    }

    public function line(): int
    {
        return $this->line;
    }
}
