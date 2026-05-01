<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;

use function sort;
use function str_starts_with;

final readonly class BundledNamespaces
{
    /**
     * Bundled namespaces always live under the reserved `phel.*` prefix —
     * `phel.core`, `phel.async`, `phel.json`, ... — whether shipped by Phel
     * itself or by a Phel composer package.
     */
    private const string BUNDLED_NAMESPACE_PREFIX = 'phel.';

    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
    ) {}

    /**
     * Discover every namespace shipped by Phel itself plus any installed Phel
     * package. The list seeds runtime/test/REPL loaders so fully qualified
     * references (`phel.async/delay`, `phel.json/encode`, ...) resolve without
     * forcing user code to spell out a `(:require ...)` for each bundled
     * module.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $directories = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        if ($directories === []) {
            return [];
        }

        $bundled = [];
        foreach ($this->buildFacade->getNamespaceFromDirectories($directories) as $info) {
            $ns = $info->getNamespace();
            if (str_starts_with($ns, self::BUNDLED_NAMESPACE_PREFIX)) {
                $bundled[$ns] = true;
            }
        }

        $namespaces = array_keys($bundled);
        sort($namespaces);

        return $namespaces;
    }
}
