<?php

declare(strict_types=1);

namespace Phel\Lint\Application;

use Phel\Lang\TypeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\LoadedSourceLocator;
use Throwable;

use function file_get_contents;
use function realpath;

/**
 * Reads the forms a file pulls into its own namespace with `(load ...)`, so
 * rules that ask whether the namespace uses a require or import see them.
 *
 * @internal
 */
final readonly class LoadedFormsReader
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        private LoadedSourceLocator $locator = new LoadedSourceLocator(),
    ) {}

    /**
     * @param list<bool|float|int|string|TypeInterface|null> $forms
     *
     * @return list<bool|float|int|string|TypeInterface|null>|null null when a load cannot be followed
     */
    public function read(array $forms, string $file, string $namespace): ?array
    {
        if ($namespace === '') {
            return [];
        }

        $paths = [];
        foreach ($forms as $form) {
            if (LoadForms::switchesNamespace($form, $namespace)) {
                break;
            }

            $paths = [...$paths, ...LoadForms::pathsIn($form)];
        }

        $loaded = [];
        $visited = [realpath($file) ?: $file => true];

        return $this->follow($paths, $file, $namespace, $visited, $loaded) ? $loaded : null;
    }

    /**
     * @param list<string|null>                              $paths
     * @param array<string, true>                            $visited
     * @param list<bool|float|int|string|TypeInterface|null> $loaded
     */
    private function follow(array $paths, string $file, string $namespace, array &$visited, array &$loaded): bool
    {
        foreach ($paths as $pathArg) {
            $path = $pathArg === null ? null : $this->locator->locate($namespace, $pathArg, $file);
            if ($path === null) {
                return false;
            }

            $key = realpath($path) ?: $path;
            if (isset($visited[$key])) {
                continue;
            }

            $visited[$key] = true;
            $forms = $this->readLoadedFile($path, $namespace);
            if ($forms === null) {
                return false;
            }

            $nested = [];
            foreach ($forms as $form) {
                $loaded[] = $form;
                $nested = [...$nested, ...LoadForms::pathsIn($form)];
            }

            if (!$this->follow($nested, $path, $namespace, $visited, $loaded)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A loaded file that switches namespace may switch back later, so its
     * forms cannot be split reliably: it counts as a load lint cannot follow.
     *
     * @return list<bool|float|int|string|TypeInterface|null>|null
     */
    private function readLoadedFile(string $path, string $namespace): ?array
    {
        $source = @file_get_contents($path);
        if ($source === false) {
            return null;
        }

        $forms = [];

        try {
            $read = $this->compilerFacade->readFormsBestEffort($source, $path);
            foreach ($read as $form) {
                if (LoadForms::switchesNamespace($form, $namespace)) {
                    return null;
                }

                $forms[] = $form;
            }

            return $read->getReturn() ? null : $forms;
        } catch (Throwable) {
            return null;
        }
    }
}
