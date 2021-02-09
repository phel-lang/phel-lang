<?php

declare(strict_types=1);

namespace Phel\Interop;

interface InteropConfigInterface
{
    public function targetDir(): string;

    public function prefixNamespace(): string;
}
