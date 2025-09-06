<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

use function sprintf;

final readonly class PhelFunction
{
    public function __construct(
        private string $namespace,
        private string $name,
        private string $doc,
        private string $rawDoc,
        private string $signature,
        private string $description,
        private string $groupKey = '',
        private string $githubUrl = '',
        private string $docUrl = '',
        private string $file = '',
        private int $line = 0,
    ) {
    }

    /**
     * @param  array{
     *     namespace?: string,
     *     fnNs?: string, // @deprecated Use namespace instead
     *     name?: string,
     *     fnName?: string, // @deprecated Use name instead
     *     doc?: string,
     *     rawDoc?: string,
     *     signature?: string,
     *     fnSignature?: string, // @deprecated Use signature instead
     *     desc?: string,
     *     groupKey?: string,
     *     githubUrl?: string,
     *     docUrl?: string,
     *     url?: string, // @deprecated Use githubUrl or docUrl instead
     *     file?: string,
     *     line?: int,
     * }  $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['namespace'] ?? $array['fnNs'] ?? '',
            $array['name'] ?? $array['fnName'] ?? '',
            $array['doc'] ?? '',
            $array['rawDoc'] ?? '',
            $array['signature'] ?? $array['fnSignature'] ?? '',
            $array['desc'] ?? '',
            $array['groupKey'] ?? '',
            $array['githubUrl'] ?? $array['url'] ?? '',
            $array['docUrl'] ?? '',
            $array['file'] ?? '',
            $array['line'] ?? 0,
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

    public function nameWithNamespace(): string
    {
        if ($this->namespace === 'core') {
            return $this->name;
        }

        return sprintf('%s/%s', $this->namespace, $this->name);
    }

    public function doc(): string
    {
        return $this->doc;
    }

    public function rawDoc(): string
    {
        return $this->rawDoc;
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
}
