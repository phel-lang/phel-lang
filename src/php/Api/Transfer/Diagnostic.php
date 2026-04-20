<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

final readonly class Diagnostic
{
    public const string SEVERITY_ERROR = 'error';

    public const string SEVERITY_WARNING = 'warning';

    public const string SEVERITY_INFO = 'info';

    public const string SEVERITY_HINT = 'hint';

    public function __construct(
        public string $code,
        public string $severity,
        public string $message,
        public string $uri,
        public int $startLine,
        public int $startCol,
        public int $endLine,
        public int $endCol,
    ) {}

    /**
     * @return array{
     *     code: string,
     *     severity: string,
     *     message: string,
     *     uri: string,
     *     startLine: int,
     *     startCol: int,
     *     endLine: int,
     *     endCol: int,
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity,
            'message' => $this->message,
            'uri' => $this->uri,
            'startLine' => $this->startLine,
            'startCol' => $this->startCol,
            'endLine' => $this->endLine,
            'endCol' => $this->endCol,
        ];
    }
}
