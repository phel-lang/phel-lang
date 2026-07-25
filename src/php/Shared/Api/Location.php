<?php

declare(strict_types=1);

namespace Phel\Shared\Api;

/**
 * @phpstan-type SerializedLocation array{uri: string, line: int, col: int, endLine: int, endCol: int}
 */
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
     * @return SerializedLocation
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
