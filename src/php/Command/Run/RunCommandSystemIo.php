<?php

declare(strict_types=1);

namespace Phel\Command\Run;

final class RunCommandSystemIo implements RunCommandIoInterface
{
    public function fileGetContents(string $string): string
    {
        return file_get_contents($string);
    }
}
