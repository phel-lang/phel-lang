<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use function md5;

final readonly class CachePathResolver
{
    public function __construct(
        private string $cacheDir,
    ) {}

    public function environmentPath(string $namespace): string
    {
        return $this->cacheDir . '/compiled/' . $this->mungeNamespace($namespace) . '.env.php';
    }

    public function compiledPath(string $namespace, string $sourcePath, string $suffix): string
    {
        $sourceFingerprint = substr(md5($sourcePath), 0, 8);

        return $this->cacheDir . '/compiled/' . $this->mungeNamespace($namespace) . '__' . $sourceFingerprint . $suffix;
    }

    private function mungeNamespace(string $namespace): string
    {
        return str_replace(['\\', '/'], '_', $namespace);
    }
}
