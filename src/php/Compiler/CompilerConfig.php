<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelConfig;

final class CompilerConfig extends AbstractConfig
{
    public function areAssertsEnabled(): bool
    {
        return (bool)$this->get(PhelConfig::ENABLE_ASSERTS, true);
    }
}
