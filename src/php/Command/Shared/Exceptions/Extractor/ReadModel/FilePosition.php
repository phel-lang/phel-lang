<?php

declare(strict_types=1);

namespace Phel\Command\Shared\Exceptions\Extractor\ReadModel;

final class FilePosition
{
    private string $filename;
    private int $line;

    public function __construct(string $filename, int $line)
    {
        $this->filename = $filename;
        $this->line = $line;
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
