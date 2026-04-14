<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

use function array_keys;
use function array_map;
use function count;
use function fwrite;
use function implode;
use function sprintf;

use const STDERR;

/**
 * Groups a flat list of `NamespaceInformation` entries by their namespace,
 * emits a single dependency-sorted sequence, and warns when a namespace
 * has multiple `(ns ...)` primary definitions.
 *
 * Within one namespace the primary `(ns ...)` file comes first, followed
 * by each `(in-ns ...)` secondary — callers can rely on this ordering to
 * evaluate the primary before code that joins the namespace.
 */
final readonly class NamespaceFileGrouper
{
    public function __construct(
        private NamespaceSorterInterface $namespaceSorter,
    ) {}

    /**
     * @param list<NamespaceInformation> $infos
     *
     * @return list<NamespaceInformation>
     */
    public function groupAndSort(array $infos): array
    {
        $byNamespace = [];
        $primaryDefinitions = [];

        foreach ($infos as $info) {
            $namespace = $info->getNamespace();
            $byNamespace[$namespace] ??= ['primary' => null, 'secondaries' => []];

            if ($info->isPrimaryDefinition()) {
                $primaryDefinitions[$namespace][] = $info->getFile();
                $byNamespace[$namespace]['primary'] = $info;
                continue;
            }

            $byNamespace[$namespace]['secondaries'][] = $info;
        }

        $this->warnAboutDuplicateNamespaces($primaryDefinitions);

        return $this->flatten($byNamespace);
    }

    /**
     * @param array<string, array{primary: ?NamespaceInformation, secondaries: list<NamespaceInformation>}> $byNamespace
     *
     * @return list<NamespaceInformation>
     */
    private function flatten(array $byNamespace): array
    {
        $dependencyIndex = [];
        foreach ($byNamespace as $namespace => $bucket) {
            $dependencyIndex[$namespace] = $bucket['primary']?->getDependencies() ?? [];
        }

        $orderedNamespaces = $this->namespaceSorter->sort(array_keys($dependencyIndex), $dependencyIndex);

        $result = [];
        foreach ($orderedNamespaces as $namespace) {
            $bucket = $byNamespace[$namespace] ?? null;
            if ($bucket === null) {
                continue;
            }

            if ($bucket['primary'] !== null) {
                $result[] = $bucket['primary'];
            }

            foreach ($bucket['secondaries'] as $secondary) {
                $result[] = $secondary;
            }
        }

        return $result;
    }

    /**
     * @param array<string, list<string>> $primaryDefinitions
     */
    private function warnAboutDuplicateNamespaces(array $primaryDefinitions): void
    {
        foreach ($primaryDefinitions as $namespace => $files) {
            if (count($files) <= 1) {
                continue;
            }

            $fileList = implode("\n", array_map(static fn(string $f): string => '  - ' . $f, $files));
            fwrite(STDERR, sprintf(
                "\nWARNING: Namespace '%s' is defined in multiple locations:\n%s\n"
                . "The last one will be used. Check your phel-config.php srcDirs/testDirs settings.\n",
                $namespace,
                $fileList,
            ));
        }
    }
}
