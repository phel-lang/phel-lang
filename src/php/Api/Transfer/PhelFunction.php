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
        private string $docUrl = '',
        private string $file = '',
        private int $line = 0,
        private string $fnNs = '',
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
     *     docUrl?: string,
     *     file?: string,
     *     line?: int,
     *     fnNs?: string,
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
            $array['docUrl'] ?? '',
            $array['file'] ?? '',
            $array['line'] ?? 0,
            $array['fnNs'] ?? '',
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

    public function docUrl(): string
    {
        return $this->docUrl;
    }

    public function file(): string
    {
        return $this->file;
    }

    public function line(): int
    {
        return $this->line;
    }

    public function fnNs(): string
    {
        return $this->fnNs;
    }
}
