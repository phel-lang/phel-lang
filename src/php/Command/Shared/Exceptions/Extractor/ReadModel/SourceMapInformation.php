<?php

declare(strict_types=1);

namespace Phel\Command\Shared\Exceptions\Extractor\ReadModel;

final class SourceMapInformation
{
    private string $filename;
    private string $sourceMap;

    public function __construct(string $filename, string $sourceMap)
    {
        $this->filename = $filename;
        $this->sourceMap = $sourceMap;
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function sourceMap(): string
    {
        return $this->sourceMap;
    }
}
