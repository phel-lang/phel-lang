<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use Phel\Build\Domain\Graph\DependencyGraph;
use Phel\Build\Domain\Graph\DependencyGraphCacheInterface;
use Phel\Build\Domain\Graph\FileSetSnapshot;

use function dirname;
use function function_exists;
use function is_array;

final class PhpDependencyGraphCache implements DependencyGraphCacheInterface
{
    private const string FORMAT_VERSION = '1.0';

    private bool $dirty = false;

    private bool $shutdownRegistered = false;

    private ?DependencyGraph $graph = null;

    private ?FileSetSnapshot $fileSet = null;

    public function __construct(
        private readonly string $cacheFile,
        private readonly string $phelVersion = '',
    ) {
        $this->loadFromFile();
    }

    public function load(): ?DependencyGraph
    {
        return $this->graph;
    }

    public function loadFileSet(): ?FileSetSnapshot
    {
        return $this->fileSet;
    }

    public function save(DependencyGraph $graph, FileSetSnapshot $fileSet): void
    {
        $this->graph = $graph;
        $this->fileSet = $fileSet;
        $this->dirty = true;
        $this->registerShutdown();
    }

    public function clear(): void
    {
        $this->graph = null;
        $this->fileSet = null;
        $this->dirty = false;

        if (file_exists($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
    }

    public function flush(): void
    {
        if (!$this->dirty) {
            return;
        }

        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            $oldUmask = umask(0);
            @mkdir($dir, 0755, true);
            umask($oldUmask);
        }

        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        $handle = @fopen($this->cacheFile, 'c');
        if ($handle === false) {
            return;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return;
        }

        try {
            ftruncate($handle, 0);
            rewind($handle);
            $content = '<?php return ' . var_export($this->toArray(), true) . ';';
            fwrite($handle, $content);
            $this->dirty = false;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->cacheFile, true);
        }
    }

    private function getVersion(): string
    {
        return self::FORMAT_VERSION . ':' . $this->phelVersion;
    }

    private function loadFromFile(): void
    {
        if (!file_exists($this->cacheFile)) {
            return;
        }

        $data = @include $this->cacheFile;
        if (!is_array($data) || !isset($data['version']) || $data['version'] !== $this->getVersion()) {
            return;
        }

        if (isset($data['graph']) && is_array($data['graph'])) {
            $this->graph = DependencyGraph::fromArray($data['graph']);
        }

        if (isset($data['file_set']) && is_array($data['file_set'])) {
            $this->fileSet = FileSetSnapshot::fromArray($data['file_set']);
        }
    }

    /**
     * @return array{version: string, graph: ?array{nodes: array<string, array{file: string, namespace: string, mtime: int, dependencies: list<string>}>, topological_order: list<string>}, file_set: ?array{files: array<string, int>, directories: list<string>, created_at: int}}
     */
    private function toArray(): array
    {
        return [
            'version' => $this->getVersion(),
            'graph' => $this->graph?->toArray(),
            'file_set' => $this->fileSet?->toArray(),
        ];
    }

    private function registerShutdown(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        register_shutdown_function([$this, 'flush']);
        $this->shutdownRegistered = true;
    }
}
