<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

use Closure;

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
    /** @var Closure(string): void */
    private Closure $warningWriter;

    /**
     * @param (Closure(string): void)|null $warningWriter
     */
    public function __construct(
        private NamespaceSorterInterface $namespaceSorter,
        ?Closure $warningWriter = null,
    ) {
        $this->warningWriter = $warningWriter ?? static function (string $message): void {
            fwrite(STDERR, $message);
        };
    }

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
                $byNamespace[$namespace]['primary'] = $this->preferLocalOverPhar(
                    $byNamespace[$namespace]['primary'],
                    $info,
                );
                continue;
            }

            $byNamespace[$namespace]['secondaries'][] = $info;
        }

        $this->warnAboutDuplicateNamespaces($primaryDefinitions);

        return $this->flatten($byNamespace);
    }

    /**
     * When the same namespace is defined both inside a PHAR (the bundled
     * stdlib) and on the local filesystem, the user's local copy always
     * wins — users put files on disk to override, not to be overridden
     * by the bundle.
     */
    private function preferLocalOverPhar(
        ?NamespaceInformation $current,
        NamespaceInformation $candidate,
    ): NamespaceInformation {
        if (!$current instanceof NamespaceInformation) {
            return $candidate;
        }

        $currentInPhar = str_starts_with($current->getFile(), 'phar://');
        $candidateInPhar = str_starts_with($candidate->getFile(), 'phar://');

        if ($currentInPhar && !$candidateInPhar) {
            return $candidate;
        }

        if (!$currentInPhar && $candidateInPhar) {
            return $current;
        }

        // Same origin — keep last-wins behavior so genuine config duplicates
        // still surface via warnAboutDuplicateNamespaces.
        return $candidate;
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
            $effective = $this->dropPharShadowedFiles($files);
            if (count($effective) <= 1) {
                continue;
            }

            $fileList = implode("\n", array_map(static fn(string $f): string => '  - ' . $f, $effective));
            ($this->warningWriter)(sprintf(
                "\nWARNING: Namespace '%s' is defined in multiple locations:\n%s\n"
                . "The last one will be used. Check your phel-config.php srcDirs/testDirs settings.\n",
                $namespace,
                $fileList,
            ));
        }
    }

    /**
     * Drop phar:// entries when a non-phar entry for the same namespace
     * exists. The local copy already wins in the grouping step, so the
     * warning would be noise for the user.
     *
     * @param list<string> $files
     *
     * @return list<string>
     */
    private function dropPharShadowedFiles(array $files): array
    {
        $hasLocal = false;
        foreach ($files as $file) {
            if (!str_starts_with($file, 'phar://')) {
                $hasLocal = true;
                break;
            }
        }

        if (!$hasLocal) {
            return $files;
        }

        return array_values(array_filter(
            $files,
            static fn(string $file): bool => !str_starts_with($file, 'phar://'),
        ));
    }
}
