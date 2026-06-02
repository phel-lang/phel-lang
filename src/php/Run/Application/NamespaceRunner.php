<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Lang\LoadClasspath;
use Phel\Run\Domain\Runner\NamespaceRunnerInterface;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Munge;

final readonly class NamespaceRunner implements NamespaceRunnerInterface
{
    public function __construct(
        private CommandFacadeInterface $commandFacade,
        private BuildFacadeInterface $buildFacade,
    ) {}

    public function run(string $namespace): void
    {
        // Normalize backslash form (e.g. `foo\bar`) to dot form (`foo.bar`) so
        // that both separators work consistently, matching the CLI RunCommand.
        $namespace = Munge::canonicalNs($namespace);

        $srcDirectories = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        // Publish the classpath so `(load ...)` forms inside the namespace
        // (or any of its dependencies) can find their sibling files.
        LoadClasspath::publish($srcDirectories);

        // `data-readers.phel` must be evaluated before the user namespace
        // so its `(register-tag ...)` calls are visible to the reader when
        // the user source is compiled.
        $dataReadersLoader = new DataReadersLoader($this->buildFacade);
        $dataReadersLoader->load($srcDirectories);

        $namespaceInformation = $this->buildFacade->getDependenciesForNamespace(
            $srcDirectories,
            [$namespace, 'phel.core'],
        );

        foreach ($namespaceInformation as $info) {
            $this->buildFacade->evalFile($info->getFile());
        }
    }
}
