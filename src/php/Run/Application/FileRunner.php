<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Compiler\Domain\Analyzer\Resolver\LoadClasspath;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Throwable;

use function dirname;
use function is_file;
use function str_replace;

final readonly class FileRunner
{
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
    ) {}

    public function run(string $filename): void
    {
        $scriptInfo = $this->buildFacade->getNamespaceFromFile($filename);
        $namespace = $scriptInfo->getNamespace();
        $scriptDir = dirname($filename);

        $primaryDirs = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        LoadClasspath::publish([...$primaryDirs, $scriptDir]);
        new DataReadersLoader($this->buildFacade)->load($primaryDirs);

        // Seed the dependency walk with both the script namespace and its
        // direct requires: an ad-hoc script (in dirname rather than srcDirs)
        // is not itself discoverable, so its transitive deps would otherwise
        // be missed even when they live under configured `srcDirs`/vendor.
        $primaryInfos = $this->buildFacade->getDependenciesForNamespace(
            $primaryDirs,
            [$namespace, 'phel.core', ...$scriptInfo->getDependencies()],
        );

        $resolved = [];
        foreach ($primaryInfos as $info) {
            $resolved[$info->getNamespace()] = $info;
        }

        if (isset($resolved[$namespace])) {
            $this->evalAll($primaryInfos);
            return;
        }

        $fallbackInfos = $this->resolveAdHocFallback($scriptInfo, $scriptDir, $resolved);

        $this->evalAll($primaryInfos);
        $this->evalAll($fallbackInfos);
        $this->buildFacade->evalFile($scriptInfo->getFile());
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
     * Resolves the script's transitive `(:require ...)` chain by name-matching
     * against `$fallbackDir`. The script itself is excluded; the caller
     * evaluates it last so it lands in the user-visible namespace. Walking
     * the dependency graph this way avoids handing `dirname($filename)` to
     * the recursive extractor, which would emit duplicate-namespace warnings
     * for unrelated siblings sitting next to the script.
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
        $queue = $scriptInfo->getDependencies();
        $found = [];
        $seen = $alreadyResolved + [$scriptInfo->getNamespace() => true];

        while ($queue !== []) {
            $ns = array_shift($queue);
            if (isset($seen[$ns])) {
                continue;
            }

            $seen[$ns] = true;

            $path = $this->namespaceToFile($fallbackDir, $ns);
            if ($path === null) {
                continue;
            }

            try {
                $info = $this->buildFacade->getNamespaceFromFile($path);
            } catch (Throwable) {
                continue;
            }

            $found[] = $info;
            foreach ($info->getDependencies() as $dep) {
                if (!isset($seen[$dep])) {
                    $queue[] = $dep;
                }
            }
        }

        return $found;
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
