<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Compiler\Domain\Analyzer\Resolver\LoadClasspath;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;

use function dirname;

final readonly class FileRunner
{
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
    ) {}

    public function run(string $filename): void
    {
        $namespace = $this->buildFacade->getNamespaceFromFile($filename)->getNamespace();

        $directories = [
            dirname($filename),
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        LoadClasspath::publish($directories);

        $infos = $this->buildFacade->getDependenciesForNamespace($directories, [$namespace, 'phel\\core']);
        foreach ($infos as $info) {
            $this->buildFacade->evalFile($info->getFile());
        }
    }
}
