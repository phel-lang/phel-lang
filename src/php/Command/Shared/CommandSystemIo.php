<?php

declare(strict_types=1);

namespace Phel\Command\Shared;

final class CommandSystemIo implements CommandIoInterface
{
    public function fileGetContents(string $string): string
    {
        return file_get_contents($string);
    }

    public function output(string $string): void
    {
        print $string;
    }
}
