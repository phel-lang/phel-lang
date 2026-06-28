<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Lang\LoadClasspath;
use Phel\Lang\Registry;
use Phel\Shared\CompilerConstants;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\NamespaceInformation;
use Throwable;

use function array_unique;
use function array_values;
use function dirname;
use function is_file;
use function str_replace;
use function str_starts_with;

final readonly class FileRunner
{
    private const string PHEL_PREFIX = 'phel.';

    private const string CLOJURE_PREFIX = 'clojure.';

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
     * Seeds the dependency walk. The union of four sources, in order:
     *
     * - the script's own namespace;
     * - {@see CompilerConstants::PHEL_CORE_NAMESPACE}, always present;
     * - bundled `phel.*` modules referenced via fully qualified form (so
     *   `phel.async/delay` resolves without an explicit require) plus
     *   Clojure-compatible requires remapped to their Phel equivalent
     *   (e.g. `clojure.test` -> `phel.test`);
     * - the script's own direct requires, kept because an ad-hoc script in
     *   `dirname` rather than a configured `srcDir` is not itself
     *   discoverable, so its transitive deps would otherwise be missed even
     *   when they live under `srcDirs`/vendor. Missing seeds are ignored by
     *   dependency resolution, so raw `clojure.*` deps are preserved.
     *
     * @return list<string>
     */
    private function primarySeeds(NamespaceInformation $scriptInfo, string $filename): array
    {
        $dependencies = $scriptInfo->getDependencies();

        return array_values(array_unique([
            $scriptInfo->getNamespace(),
            CompilerConstants::PHEL_CORE_NAMESPACE,
            ...$this->bundledNamespaceDetector->detect($filename),
            ...$this->bundledNamespaceDetector->remapClojureDependencies($dependencies),
            ...$dependencies,
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
            $this->collectAdHocDep($dep, $scriptInfo->getNamespace(), $fallbackDir, $seen, $found);
        }

        return $found;
    }

    /**
     * @param array<string, mixed>       $seen
     * @param list<NamespaceInformation> $found
     */
    private function collectAdHocDep(
        string $namespace,
        string $scriptNamespace,
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
            // Not a sibling of the script. Tolerate it only when it resolves
            // some other way (bundled `phel.*`, a `clojure.*` remap, or an
            // already-loaded namespace); a require that resolves nowhere is
            // broken and previously exited 0 with no feedback.
            if (!$this->isResolvableWithoutSibling($namespace)) {
                throw ExtractorException::cannotResolveRequiredNamespace($namespace, $scriptNamespace);
            }

            return;
        }

        try {
            $info = $this->buildFacade->getNamespaceFromFile($path);
        } catch (Throwable) {
            return;
        }

        foreach ($info->getDependencies() as $dep) {
            $this->collectAdHocDep($dep, $info->getNamespace(), $fallbackDir, $seen, $found);
        }

        $found[] = $info;
    }

    /**
     * Whether a non-sibling dependency still resolves: a framework-provided
     * `phel.*`/`clojure.*` namespace (bundled stdlib loaded lazily, or a
     * clojure-compat shim), or a namespace already in the runtime registry.
     * Mirrors the tolerance in {@see \Phel\Build\Application\DependenciesForNamespace}
     * so both resolution paths agree on what counts as a broken require.
     */
    private function isResolvableWithoutSibling(string $namespace): bool
    {
        $canonical = str_replace('\\', '.', $namespace);

        if (str_starts_with($canonical, self::PHEL_PREFIX)
            || str_starts_with($canonical, self::CLOJURE_PREFIX)
        ) {
            return true;
        }

        return Registry::getInstance()->hasNamespace(str_replace('-', '_', $canonical));
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
