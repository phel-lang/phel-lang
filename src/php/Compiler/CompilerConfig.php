<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelConfig;

final class CompilerConfig extends AbstractConfig
{
    public function assertsEnabled(): bool
    {
        return (bool)$this->get(PhelConfig::ASSERTS_ENABLED, true);
    }
}
