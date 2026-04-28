<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

final readonly class Location
{
    public function __construct(
        public string $uri,
        public int $line,
        public int $col,
        public int $endLine = 0,
        public int $endCol = 0,
    ) {}

    /**
     * @return array{uri: string, line: int, col: int, endLine: int, endCol: int}
     */
    public function toArray(): array
    {
        return [
            'uri' => $this->uri,
            'line' => $this->line,
            'col' => $this->col,
            'endLine' => $this->endLine,
            'endCol' => $this->endCol,
        ];
    }
}
