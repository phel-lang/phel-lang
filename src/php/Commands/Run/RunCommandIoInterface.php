<?php

declare(strict_types=1);

namespace Phel\Commands\Run;

interface RunCommandIoInterface
{
    public function fileGetContents(string $path): string;
}
