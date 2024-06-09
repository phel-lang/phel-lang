<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions\Extractor\ReadModel;

final readonly class FilePosition
{
    public function __construct(
        private string $filename,
        private int $line,
    ) {
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function line(): int
    {
        return $this->line;
    }
}
