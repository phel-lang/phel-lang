<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

final readonly class Definition
{
    public const string KIND_DEFN = 'defn';

    public const string KIND_DEF = 'def';

    public const string KIND_DEFMACRO = 'defmacro';

    public const string KIND_DEFSTRUCT = 'defstruct';

    public const string KIND_DEFPROTOCOL = 'defprotocol';

    public const string KIND_DEFINTERFACE = 'definterface';

    public const string KIND_DEFEXCEPTION = 'defexception';

    public const string KIND_UNKNOWN = 'unknown';

    /**
     * @param list<string> $signature
     */
    public function __construct(
        public string $namespace,
        public string $name,
        public string $uri,
        public int $line,
        public int $col,
        public string $kind,
        public array $signature,
        public string $docstring,
        public bool $private,
    ) {}

    public function fullName(): string
    {
        return $this->namespace . '/' . $this->name;
    }

    /**
     * @return array{
     *     namespace: string,
     *     name: string,
     *     uri: string,
     *     line: int,
     *     col: int,
     *     kind: string,
     *     signature: list<string>,
     *     docstring: string,
     *     private: bool,
     * }
     */
    public function toArray(): array
    {
        return [
            'namespace' => $this->namespace,
            'name' => $this->name,
            'uri' => $this->uri,
            'line' => $this->line,
            'col' => $this->col,
            'kind' => $this->kind,
            'signature' => $this->signature,
            'docstring' => $this->docstring,
            'private' => $this->private,
        ];
    }
}
