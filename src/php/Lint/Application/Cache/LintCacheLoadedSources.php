<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Cache;

use Phel\Lint\Application\LoadForms;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\LoadedSourceLocator;
use Throwable;

use function array_key_exists;

/**
 * @internal
 */
final class LintCacheLoadedSources
{
    /** @var array<string, list<string>|null> */
    private array $loads = [];

    public function __construct(
        private readonly CompilerFacadeInterface $compilerFacade,
        private readonly LoadedSourceLocator $locator = new LoadedSourceLocator(),
    ) {}

    /**
     * @return array<string, string>|null
     */
    public function hashesFor(string $file, string $namespace): ?array
    {
        $hashes = [];

        return $this->collect($file, $namespace, $hashes) ? $hashes : null;
    }

    /**
     * @param array<string, string> $hashes
     */
    private function collect(string $file, string $namespace, array &$hashes): bool
    {
        $file = realpath($file) ?: $file;
        if (isset($hashes[$file])) {
            return true;
        }

        $source = @file_get_contents($file);
        if ($source === false) {
            return false;
        }

        $hashes[$file] = md5($source);
        if (preg_match('/\bload\b/', $source) !== 1) {
            return true;
        }

        $key = $file . '|' . $namespace . '|' . $hashes[$file];
        if (!array_key_exists($key, $this->loads)) {
            $this->loads[$key] = $this->findLoads($source, $file, $namespace);
        }

        $paths = $this->loads[$key];
        if ($paths === null) {
            return false;
        }

        return array_all($paths, function (string $path) use ($namespace, &$hashes): bool {
            return $this->collect($path, $namespace, $hashes);
        });
    }

    /**
     * @return list<string>|null
     */
    private function findLoads(string $source, string $file, string $namespace): ?array
    {
        $paths = [];

        try {
            $forms = $this->compilerFacade->readFormsBestEffort($source, $file);
            foreach ($forms as $form) {
                if (LoadForms::switchesNamespace($form, $namespace)) {
                    return null;
                }

                foreach (LoadForms::pathsIn($form) as $pathArg) {
                    $path = $pathArg === null ? null : $this->locator->locate($namespace, $pathArg, $file);
                    if ($path === null) {
                        return null;
                    }

                    $paths[] = $path;
                }
            }

            return $forms->getReturn() ? null : $paths;
        } catch (Throwable) {
            return null;
        }
    }
}
