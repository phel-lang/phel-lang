<?php

declare(strict_types=1);

namespace Phel\Lint\Infrastructure;

use Phel\Lint\Domain\KnownNamespacesInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Shared\Munge;

use function array_map;

/**
 * Lists the declared namespaces once per lint run, on first use.
 *
 * @internal
 */
final class ProjectKnownNamespaces implements KnownNamespacesInterface
{
    /** @var list<string>|null */
    private ?array $namespaces = null;

    public function __construct(
        private readonly RunFacadeInterface $runFacade,
    ) {}

    public function all(): array
    {
        return $this->namespaces ??= array_map(Munge::canonicalNs(...), $this->runFacade->getAllNamespaces());
    }
}
