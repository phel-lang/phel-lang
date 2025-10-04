<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel;
use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Run\RunConfig;
use Phel\Shared\CompilerConstants;

use function dirname;
use function file_exists;
use function getcwd;

final readonly class NamespaceLoader
{
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
        private RunConfig $config,
    ) {
    }

    public function loadPhelNamespaces(?string $replStartupFile = null): void
    {
        if ($replStartupFile === null) {
            $replStartupFile = $this->config->getReplStartupFile();
        }

        if (!file_exists($replStartupFile)) {
            return;
        }

        $namespace = $this->buildFacade
            ->getNamespaceFromFile($replStartupFile)
            ->getNamespace();

        $srcDirectories = [
            dirname($replStartupFile),
            ...$this->commandFacade->getAllPhelDirectories(),
        ];

        $namespaceInformation = $this->buildFacade->getDependenciesForNamespace(
            $srcDirectories,
            [$namespace, 'phel\\core'],
        );

        foreach ($namespaceInformation as $info) {
            $this->buildFacade->evalFile($info->getFile());
        }

        Phel::addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, '*file*', '');

        $cwd = getcwd();
        if ($cwd !== false) {
            $srcDirectories[] = $cwd;
        }

        // Hack: Set source directories for the repl
        Phel::addDefinition('phel\\repl', 'src-dirs', $srcDirectories);
    }
}
