<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Compiler\Domain\Analyzer\Resolver\LoadClasspath;
use Phel\Shared\CompilerConstants;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Throwable;

use function array_unique;
use function array_values;
use function dirname;
use function is_file;
use function str_replace;

final readonly class FileRunner
{
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
        private BundledNamespaceDetector $bundledNamespaceDetector,
    ) {}

    public function run(string $filename): void
    {
        $scriptInfo = $this->buildFacade->getNamespaceFromFile($filename);
        $scriptDir = dirname($filename);

        $primaryDirs = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        LoadClasspath::publish([...$primaryDirs, $scriptDir]);
        new DataReadersLoader($this->buildFacade)->load($primaryDirs);

        $primaryInfos = $this->buildFacade->getDependenciesForNamespace(
            $primaryDirs,
            $this->primarySeeds($scriptInfo, $filename),
        );

        $resolved = $this->indexByNamespace($primaryInfos);

        if (isset($resolved[$scriptInfo->getNamespace()])) {
            $this->evalAll($primaryInfos);
            return;
        }

        $fallbackInfos = $this->resolveAdHocFallback($scriptInfo, $scriptDir, $resolved);
        $this->evalAll([...$primaryInfos, ...$fallbackInfos, $scriptInfo]);
    }

    /**
     * Seed the dependency walk with the script namespace, only the bundled
     * `phel.*` modules the script actually references via fully qualified
     * form (so `phel.async/delay` resolves without an explicit require),
     * and the script's direct requires. The script's direct requires are
     * kept because an ad-hoc script in `dirname` rather than configured
     * `srcDirs` is not itself discoverable, so its transitive deps would
     * otherwise be missed even when they live under `srcDirs`/vendor.
     *
     * @return list<string>
     */
    private function primarySeeds(NamespaceInformation $scriptInfo, string $filename): array
    {
        return array_values(array_unique([
            $scriptInfo->getNamespace(),
            CompilerConstants::PHEL_CORE_NAMESPACE,
            ...$this->bundledNamespaceDetector->detect($filename),
            ...$scriptInfo->getDependencies(),
        ]));
    }

    /**
     * @param list<NamespaceInformation> $infos
     */
    private function evalAll(array $infos): void
    {
        foreach ($infos as $info) {
            $this->buildFacade->evalFile($info->getFile());
        }
    }

    /**
     * @param list<NamespaceInformation> $infos
     *
     * @return array<string, NamespaceInformation>
     */
    private function indexByNamespace(array $infos): array
    {
        $indexed = [];
        foreach ($infos as $info) {
            $indexed[$info->getNamespace()] = $info;
        }

        return $indexed;
    }

    /**
     * Resolves the script's transitive `(:require ...)` chain by name-matching
     * against `$fallbackDir`. The script itself is excluded; the caller
     * evaluates it last so it lands in the user-visible namespace. Walking
     * the dependency graph this way avoids handing `dirname($filename)` to
     * the recursive extractor, which would emit duplicate-namespace warnings
     * for unrelated siblings sitting next to the script. DFS post-order
     * guarantees each dep is appended after its own deps, so multi-hop
     * ad-hoc chains (a -> b -> c) evaluate in dependency-first order.
     *
     * @param array<string, NamespaceInformation> $alreadyResolved
     *
     * @return list<NamespaceInformation>
     */
    private function resolveAdHocFallback(
        NamespaceInformation $scriptInfo,
        string $fallbackDir,
        array $alreadyResolved,
    ): array {
        $found = [];
        $seen = $alreadyResolved + [$scriptInfo->getNamespace() => true];

        foreach ($scriptInfo->getDependencies() as $dep) {
            $this->collectAdHocDep($dep, $fallbackDir, $seen, $found);
        }

        return $found;
    }

    /**
     * @param array<string, mixed>       $seen
     * @param list<NamespaceInformation> $found
     */
    private function collectAdHocDep(
        string $namespace,
        string $fallbackDir,
        array &$seen,
        array &$found,
    ): void {
        if (isset($seen[$namespace])) {
            return;
        }

        $seen[$namespace] = true;

        $path = $this->namespaceToFile($fallbackDir, $namespace);
        if ($path === null) {
            return;
        }

        try {
            $info = $this->buildFacade->getNamespaceFromFile($path);
        } catch (Throwable) {
            return;
        }

        foreach ($info->getDependencies() as $dep) {
            $this->collectAdHocDep($dep, $fallbackDir, $seen, $found);
        }

        $found[] = $info;
    }

    /**
     * Maps a dot-form namespace to its on-disk relative path under
     * `$dir`. Dashes survive (`my-helper.util` -> `my-helper/util.phel`)
     * because Phel filenames preserve dashes; the analyzer munges them
     * to underscores only at PHP-emit time.
     */
    private function namespaceToFile(string $dir, string $namespace): ?string
    {
        $relative = str_replace('.', DIRECTORY_SEPARATOR, $namespace) . '.phel';
        $candidate = $dir . DIRECTORY_SEPARATOR . $relative;

        return is_file($candidate) ? $candidate : null;
    }
}
