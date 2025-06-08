<?php

declare(strict_types=1);

namespace Phel\Run\Domain;

interface NamespacesLoaderInterface
{
    /**
     * @return list<string>
     */
    public function getLoadedNamespaces(): array;
}
