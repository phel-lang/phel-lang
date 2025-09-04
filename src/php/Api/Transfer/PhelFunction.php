<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

final readonly class PhelFunction
{
    public function __construct(
        private string $namespace,
        private string $name,
        private string $doc,
        private string $signature,
        private string $description,
        private string $groupKey = '',
        private string $githubUrl = '',
        private string $docUrl = '',
        private string $file = '',
        private int $line = 0,
        private string $docString = '',
    ) {
    }

    /**
     * @param array{
     *     namespace?: string,
     *     fnNs?: string, // @deprecated Use namespace instead
     *     name?: string,
     *     fnName?: string, // @deprecated Use name instead
     *     doc?: string,
     *     signature?: string,
     *     fnSignature?: string, // @deprecated Use signature instead
     *     desc?: string,
     *     groupKey?: string,
     *     githubUrl?: string,
     *     docUrl?: string,
     *     url?: string, // @deprecated Use githubUrl or docUrl instead
     *     file?: string,
     *     line?: int,
     *     docString?: string,
     * } $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['namespace'] ?? $array['fnNs'] ?? '',
            $array['name'] ?? $array['fnName'] ?? '',
            $array['doc'] ?? '',
            $array['signature'] ?? $array['fnSignature'] ?? '',
            $array['desc'] ?? '',
            $array['groupKey'] ?? '',
            $array['githubUrl'] ?? $array['url'] ?? '',
            $array['docUrl'] ?? '',
            $array['file'] ?? '',
            $array['line'] ?? 0,
            $array['docString'] ?? ''
        );
    }

    /**
     * @deprecated Use name() instead
     */
    public function fnName(): string
    {
        return $this->name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function doc(): string
    {
        return $this->doc;
    }

    public function fnSignature(): string
    {
        return $this->signature;
    }

    public function signature(): string
    {
        return $this->signature;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function groupKey(): string
    {
        return $this->groupKey;
    }

    public function githubUrl(): string
    {
        return $this->githubUrl;
    }

    /**
     * @deprecated Use githubUrl() instead
     */
    public function url(): string
    {
        return $this->githubUrl;
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

    /**
     * @deprecated Use namespace() instead
     */
    public function fnNs(): string
    {
        return $this->namespace;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }
    
    public function docString(): string
    {
        return $this->docString;
    }
}
