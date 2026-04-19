<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

use function sprintf;

final readonly class PhelFunction
{
    /**
     * @param list<string>         $signatures
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $namespace,
        public string $name,
        public string $doc,
        public array $signatures,
        public string $description,
        public string $groupKey = '',
        public string $githubUrl = '',
        public string $docUrl = '',
        public string $file = '',
        public int $line = 0,
        public array $meta = [],
    ) {}

    /**
     * @param  array{
     *     namespace?: string,
     *     name?: string,
     *     doc?: string,
     *     signatures?: list<string>,
     *     desc?: string,
     *     groupKey?: string,
     *     githubUrl?: string,
     *     docUrl?: string,
     *     file?: string,
     *     line?: int,
     *     meta?: array<string, mixed>,
     * }  $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['namespace'] ?? '',
            $array['name'] ?? '',
            $array['doc'] ?? '',
            $array['signatures'] ?? [],
            $array['desc'] ?? '',
            $array['groupKey'] ?? '',
            $array['githubUrl'] ?? '',
            $array['docUrl'] ?? '',
            $array['file'] ?? '',
            $array['line'] ?? 0,
            $array['meta'] ?? [],
        );
    }

    public function nameWithNamespace(): string
    {
        if ($this->namespace === '' || $this->namespace === 'core') {
            return $this->name;
        }

        return sprintf('%s/%s', $this->namespace, $this->name);
    }
}
