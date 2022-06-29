<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Shared\Exceptions\Extractor\ReadModel;

final class FilePosition
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
