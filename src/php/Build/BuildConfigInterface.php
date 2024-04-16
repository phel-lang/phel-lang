<?php

declare(strict_types=1);

namespace Phel\Build;

interface BuildConfigInterface
{
    /**
     * @return list<string>
     */
    public function getPathsToIgnore(): array;

    /**
     * @return list<string>
     */
    public function getPathsToAvoidCache(): array;

    public function shouldCreateEntryPointPhpFile(): bool;
}
