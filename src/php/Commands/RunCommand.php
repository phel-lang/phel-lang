<?php

declare(strict_types=1);

namespace Phel\Commands;

use Phel\Runtime;
use RuntimeException;

final class RunCommand
{
    public const NAME = 'run';

    private ?Runtime $runtime;

    public function __construct(?Runtime $runtime = null)
    {
        $this->runtime = $runtime;
    }

    public function run(string $currentDirectory, string $fileOrPath): void
    {
        $ns = $fileOrPath;

        if (file_exists($fileOrPath)) {
            $ns = CommandUtils::getNamespaceFromFile($fileOrPath);
        }

        $rt = $this->runtime ?? CommandUtils::loadRuntime($currentDirectory);

        $result = $rt->loadNs($ns);

        if (!$result) {
            throw new RuntimeException('Cannot load namespace: ' . $ns);
        }
    }
}
