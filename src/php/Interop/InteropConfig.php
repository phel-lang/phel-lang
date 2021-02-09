<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\AbstractPhelConfig;

final class InteropConfig extends AbstractPhelConfig implements InteropConfigInterface
{
    public function targetDir(): string
    {
        return (string)($this->get('export')['target-directory'] ?? '');
    }

    public function prefixNamespace(): string
    {
        return (string)($this->get('export')['prefix-namespace'] ?? '');
    }
}
