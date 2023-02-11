<?php

declare(strict_types=1);

namespace Phel\Shared;

interface BuildConstants
{
    /** @deprecated Use BuildConstants::BUILD_MODE instead */
    public const COMPILE_MODE = '*compile-mode*';

    public const BUILD_MODE = '*build-mode*';
}
