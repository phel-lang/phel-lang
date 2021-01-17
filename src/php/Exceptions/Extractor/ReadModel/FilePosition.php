<?php

declare(strict_types=1);

namespace Phel\Exceptions\Extractor\ReadModel;

final class FilePosition
{
    private string $fileName;
    private int $line;

    public function __construct(string $fileName, int $line)
    {
        $this->fileName = $fileName;
        $this->line = $line;
    }

    public function fileName(): string
    {
        return $this->fileName;
    }

    public function line(): int
    {
        return $this->line;
    }
}
