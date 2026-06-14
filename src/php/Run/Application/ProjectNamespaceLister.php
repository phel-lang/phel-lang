<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\NamespaceInformation;

use function array_keys;
use function sort;

final readonly class ProjectNamespaceLister
{
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
    ) {}

    /**
     * Distinct, sorted namespace names declared across the project's source,
     * test, and vendor source directories. Used to power shell completion for
     * `phel run` / `phel test`.
     *
     * @return list<string>
     */
    public function listAll(): array
    {
        $directories = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getTestDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        $namespaces = [];
        foreach ($this->buildFacade->getNamespaceFromDirectories($directories) as $info) {
            /** @var NamespaceInformation $info */
            $namespaces[$info->getNamespace()] = true;
        }

        $names = array_keys($namespaces);
        sort($names);

        return $names;
    }
}
