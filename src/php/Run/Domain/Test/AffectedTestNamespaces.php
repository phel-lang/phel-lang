<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Test;

use Phel\Shared\NamespaceInformation;

use function array_key_exists;
use function array_keys;
use function array_shift;
use function realpath;
use function str_starts_with;

/**
 * Which test namespaces a set of changed namespaces can affect: the changed
 * namespaces themselves plus everything that transitively requires one of
 * them, restricted to the namespaces under the test roots. Pure: works on
 * the scan result, so it needs neither the compiled cache nor its dependency
 * graph, and behaves the same on a cold cache.
 *
 * @internal
 */
final readonly class AffectedTestNamespaces
{
    /**
     * @param list<NamespaceInformation> $infos             every namespace of the run, as scanned
     * @param list<string>               $changedNamespaces the namespaces of the changed files
     * @param list<string>               $testRoots         absolute test directories
     *
     * @return list<string> test namespace names, in scan order
     */
    public function select(array $infos, array $changedNamespaces, array $testRoots): array
    {
        $dependents = [];
        foreach ($infos as $info) {
            foreach ($info->getDependencies() as $dependency) {
                $dependents[$dependency][] = $info->getNamespace();
            }
        }

        $affected = [];
        $queue = [];
        foreach ($changedNamespaces as $namespace) {
            if (!array_key_exists($namespace, $affected)) {
                $affected[$namespace] = true;
                $queue[] = $namespace;
            }
        }

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($dependents[$current] ?? [] as $dependent) {
                if (!array_key_exists($dependent, $affected)) {
                    $affected[$dependent] = true;
                    $queue[] = $dependent;
                }
            }
        }

        $roots = [];
        foreach ($testRoots as $root) {
            $roots[] = $this->normalize($root);
        }

        $selected = [];
        foreach ($infos as $info) {
            if (!array_key_exists($info->getNamespace(), $affected)) {
                continue;
            }

            foreach ($roots as $root) {
                if (str_starts_with($this->normalize($info->getFile()), $root . '/')) {
                    $selected[$info->getNamespace()] = true;
                    break;
                }
            }
        }

        return array_keys($selected);
    }

    private function normalize(string $path): string
    {
        $real = realpath($path);

        return $real === false ? $path : $real;
    }
}
