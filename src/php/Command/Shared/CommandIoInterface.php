<?php

declare(strict_types=1);

namespace Phel\Command\Shared;

interface CommandIoInterface
{
    public function createDirectory(string $directory): void;

    public function fileGetContents(string $filename): string;

    public function filePutContents(string $filename, string $content): void;

    public function writeln(string $string): void;
}
