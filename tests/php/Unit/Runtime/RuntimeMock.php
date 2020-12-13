<?php

declare(strict_types=1);

namespace PhelTest\Unit\Runtime;

use Phel\Compiler\GlobalEnvironment;
use Phel\Runtime\Runtime;

final class RuntimeMock extends Runtime
{
    public array $files = [];
    public ?string $loadedFile = null;

    public function __construct()
    {
        $this->globalEnv = new GlobalEnvironment();
    }

    public function setFiles(array $files): void
    {
        $this->files = $files;
    }

    protected function loadFile(string $filename, string $ns): void
    {
        $this->loadedFile = $filename;
    }

    protected function fileExists($filename): bool
    {
        return in_array($filename, $this->files);
    }
}
