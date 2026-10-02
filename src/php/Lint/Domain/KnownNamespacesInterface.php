<?php

declare(strict_types=1);

namespace Phel\Lint\Domain;

/**
 * @internal
 */
interface KnownNamespacesInterface
{
    /**
     * Every namespace declared across the project's source, test and vendor
     * directories, Phel's own stdlib included, in canonical dot form.
     *
     * @return list<string>
     */
    public function all(): array;
}
