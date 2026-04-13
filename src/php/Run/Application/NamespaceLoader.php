<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel;
use Phel\Shared\CompilerConstants;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;

use function dirname;
use function file_exists;
use function getcwd;

final class NamespaceLoader
{
    private static array $loadedFiles = [];

    public function __construct(
        private readonly BuildFacadeInterface $buildFacade,
        private readonly CommandFacadeInterface $commandFacade,
        private readonly string $defaultReplStartupFile,
    ) {}

    public static function reset(): void
    {
        self::$loadedFiles = [];
    }

    public function loadPhelNamespaces(?string $replStartupFile = null): void
    {
        if ($replStartupFile === null) {
            $replStartupFile = $this->defaultReplStartupFile;
        }

        if (!file_exists($replStartupFile)) {
            return;
        }

        $namespace = $this->buildFacade
            ->getNamespaceFromFile($replStartupFile)
            ->getNamespace();

        $srcDirectories = $this->buildSrcDirectories($replStartupFile);

        // Populate `phel\repl/src-dirs` before evaluating any file so that
        // `(load ...)` calls inside core.phel (or any other namespace) can
        // resolve classpath-relative paths against the search roots.
        Phel::addDefinition('phel\\repl', 'src-dirs', $srcDirectories);

        $namespaceInformation = $this->buildFacade->getDependenciesForNamespace(
            $srcDirectories,
            [$namespace, 'phel\\core'],
        );

        foreach ($namespaceInformation as $info) {
            $file = $info->getFile();
            if (!isset(self::$loadedFiles[$file])) {
                $this->buildFacade->evalFile($file);
                self::$loadedFiles[$file] = true;
            }
        }

        Phel::addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, '*file*', '');
    }

    /**
     * @return list<string>
     */
    private function buildSrcDirectories(string $replStartupFile): array
    {
        $srcDirectories = [
            dirname($replStartupFile),
            ...$this->commandFacade->getAllPhelDirectories(),
        ];

        $cwd = getcwd();
        if ($cwd !== false) {
            $srcDirectories[] = $cwd;
        }

        return $srcDirectories;
    }
}
