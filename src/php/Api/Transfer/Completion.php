<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

final readonly class Completion
{
    public const string KIND_LOCAL = 'local';

    public const string KIND_GLOBAL = 'global';

    public const string KIND_REQUIRE = 'require';

    public const string KIND_MACRO = 'macro';

    public const string KIND_KEYWORD = 'keyword';

    public function __construct(
        public string $label,
        public string $kind,
        public string $detail = '',
        public string $documentation = '',
    ) {}

    /**
     * @return array{label: string, kind: string, detail: string, documentation: string}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'kind' => $this->kind,
            'detail' => $this->detail,
            'documentation' => $this->documentation,
        ];
    }
}
