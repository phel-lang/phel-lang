<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel\Lang\SourceLocation;

use function dirname;

final class MagicConstantResolver
{
    public function resolveFile(?SourceLocation $sl): ?string
    {
        return $this->resolveSourceString($sl)
            ?? $this->resolveRealpath($sl);
    }

    public function resolveDir(?SourceLocation $sl): ?string
    {
        return $this->resolveSourceString($sl)
            ?? $this->resolveRealpathDirname($sl);
    }

    private function resolveSourceString(?SourceLocation $sl): ?string
    {
        return ($sl instanceof SourceLocation && $sl->getFile() === 'string') ? '' : null;
    }

    private function resolveRealpath(?SourceLocation $sl): ?string
    {
        if (!$sl instanceof SourceLocation) {
            return null;
        }

        $realpath = realpath($sl->getFile());

        return $realpath === false ? null : $realpath;
    }

    private function resolveRealpathDirname(?SourceLocation $sl): ?string
    {
        if (!$sl instanceof SourceLocation) {
            return null;
        }

        $realpath = realpath(dirname($sl->getFile()));

        return $realpath === false ? null : $realpath;
    }
}
