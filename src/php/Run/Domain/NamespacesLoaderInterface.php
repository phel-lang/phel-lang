<?php

declare(strict_types=1);

namespace Phel\Run\Domain;

use Phel\Shared\NamespaceInformation;

interface NamespacesLoaderInterface
{
    /**
     * @return list<NamespaceInformation>
     */
    public function getLoadedNamespaces(): array;
}
