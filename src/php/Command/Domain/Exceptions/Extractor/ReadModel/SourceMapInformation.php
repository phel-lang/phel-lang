<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions\Extractor\ReadModel;

final readonly class SourceMapInformation
{
    public function __construct(
        private string $filename,
        private string $sourceMap,
    ) {
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
