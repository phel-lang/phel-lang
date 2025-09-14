<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

use function sprintf;

final readonly class PhelFunction
{
    public function __construct(
        public string $namespace,
        public string $name,
        public string $doc,
        public string $signature,
        public string $description,
        public string $groupKey = '',
        public string $githubUrl = '',
        public string $docUrl = '',
        public string $file = '',
        public int $line = 0,
    ) {
    }

    /**
     * @param  array{
     *     namespace?: string,
     *     name?: string,
     *     doc?: string,
     *     signature?: string,
     *     desc?: string,
     *     groupKey?: string,
     *     githubUrl?: string,
     *     docUrl?: string,
     *     file?: string,
     *     line?: int,
     * }  $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['namespace'] ?? '',
            $array['name'] ?? '',
            $array['doc'] ?? '',
            $array['signature'] ?? '',
            $array['desc'] ?? '',
            $array['groupKey'] ?? '',
            $array['githubUrl'] ?? '',
            $array['docUrl'] ?? '',
            $array['file'] ?? '',
            $array['line'] ?? 0,
        );
    }

    public function nameWithNamespace(): string
    {
        if ($this->namespace === '' || $this->namespace === 'core') {
            return $this->name;
        }

        return sprintf('%s/%s', $this->namespace, $this->name);
    }

    /**
     * @deprecated in favor of the name attribute
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @deprecated in favor of the doc attribute
     */
    public function doc(): string
    {
        return $this->doc;
    }

    /**
     * @deprecated in favor of the signature attribute
     */
    public function fnSignature(): string
    {
        return $this->signature;
    }

    /**
     * @deprecated in favor of the signature attribute
     */
    public function signature(): string
    {
        return $this->signature;
    }

    /**
     * @deprecated in favor of the description attribute
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * @deprecated in favor of the groupKey attribute
     */
    public function groupKey(): string
    {
        return $this->groupKey;
    }

    /**
     * @deprecated in favor of the githubUrl attribute
     */
    public function githubUrl(): string
    {
        return $this->githubUrl;
    }

    /**
     * @deprecated in favor of the docUrl attribute
     */
    public function docUrl(): string
    {
        return $this->docUrl;
    }

    /**
     * @deprecated in favor of the file attribute
     */
    public function file(): string
    {
        return $this->file;
    }

    /**
     * @deprecated in favor of the line attribute
     */
    public function line(): int
    {
        return $this->line;
    }

    /**
     * @deprecated in favor of the namespace attribute
     */
    public function namespace(): string
    {
        return $this->namespace;
    }
}
