<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\AbstractPhelConfig;
use RuntimeException;

final class InteropConfig extends AbstractPhelConfig implements InteropConfigInterface
{
    public function targetDir(): string
    {
        $targetDirectory = $this->get('export')['target-directory'] ?? '';

        if (empty($targetDirectory)) {
            throw new RuntimeException('Missing composer option: export.target-directory');
        }

        return $targetDirectory;
    }

    public function prefixNamespace(): string
    {
        return (string)($this->get('export')['namespace-prefix'] ?? '');
    }
}
