<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Cache;

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Rule\FormWalker;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\LoadedSourceLocator;
use Throwable;

use function array_key_exists;
use function is_string;

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
        $complete = true;

        try {
            $forms = $this->compilerFacade->readFormsBestEffort($source, $file);
            foreach ($forms as $form) {
                $this->collectLoads($form, $file, $namespace, $paths, $complete);
            }

            return $complete && !$forms->getReturn() ? $paths : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param list<string> $paths
     */
    private function collectLoads(mixed $form, string $file, string $namespace, array &$paths, bool &$complete): void
    {
        FormWalker::walk($form, function (mixed $node) use (&$paths, &$complete, $file, $namespace): void {
            if ($node instanceof PersistentHashSetInterface) {
                foreach ($node as $item) {
                    $this->collectLoads($item, $file, $namespace, $paths, $complete);
                }

                return;
            }

            if (!$node instanceof PersistentListInterface) {
                return;
            }

            $head = $node->first();
            if (!$head instanceof Symbol || $head->getName() !== Symbol::NAME_LOAD) {
                return;
            }

            $argument = $node->get(1);
            $path = is_string($argument) ? $this->locator->locate($namespace, $argument, $file) : null;
            if ($path === null) {
                $complete = false;
            } else {
                $paths[] = $path;
            }
        });
    }
}
