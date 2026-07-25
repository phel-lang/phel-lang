<?php

declare(strict_types=1);

namespace Phel\Lang;

interface NamedInterface
{
    public function getName(): string;

    public function getNamespace(): ?string;

    /**
     * Return the namespace and name of the object.
     */
    public function getFullName(): string;
}
