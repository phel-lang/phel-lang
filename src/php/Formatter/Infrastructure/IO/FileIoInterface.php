<?php

declare(strict_types=1);

namespace Phel\Formatter\Infrastructure\IO;

interface FileIoInterface
{
    public function getContents(string $filename): string;

    public function putContents(string $filename, string $data): void;
}
