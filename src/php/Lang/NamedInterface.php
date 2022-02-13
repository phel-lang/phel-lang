<?php

declare(strict_types=1);

namespace Phel\Lang;

interface NamedInterface
{
    /**
     * Return the name of the object.
     */
    public function getName(): string;

    /**
     * Return the namespace of the object.
     */
    public function getNamespace(): ?string;

    /**
     * Return the namespace and name of the object.
     */
    public function getFullName(): string;
}
