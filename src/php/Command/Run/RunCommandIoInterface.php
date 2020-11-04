<?php

declare(strict_types=1);

namespace Phel\Command\Run;

interface RunCommandIoInterface
{
    public function fileGetContents(string $path): string;
}
