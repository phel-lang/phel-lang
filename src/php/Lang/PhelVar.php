<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use RuntimeException;

use function sprintf;

/**
 * A first-class reference to a global definition created by `def`.
 *
 * Each `PhelVar` is a stable handle to a single registry slot identified by
 * namespace and name. The handle stays valid as the slot's value changes;
 * `deref()` always reads the current value, `alterRoot()` replaces it,
 * `meta()` returns the metadata attached at definition time.
 *
 * Equality is structural over `(ns, name)` so two handles to the same slot
 * compare equal; identity-style equality is provided by the same operator
 * since `PhelVar` has no further state.
 */
final readonly class PhelVar implements EqualsInterface, HashableInterface, MetaInterface
{
    public function __construct(
        private string $namespace,
        private string $name,
    ) {}

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Reads the metadata attached to this var's underlying definition.
     * `MetaInterface` lets `(meta #'sym)` route through the standard runtime
     * path instead of the quoted-symbol special case.
     */
    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta();
    }

    /**
     * Vars are global handles to a single registry slot, so attaching
     * different metadata per handle is meaningless. The call returns the
     * receiver unchanged. To rotate metadata on the underlying definition,
     * redefine it with `def`.
     */
    public function withMeta(?PersistentMapInterface $meta): static
    {
        return $this;
    }

    public function getFullName(): string
    {
        return $this->namespace . '/' . $this->name;
    }

    /**
     * Returns the current root value of this var. Throws if the underlying
     * definition has been removed from the registry.
     */
    public function deref(): mixed
    {
        $registry = Registry::getInstance();
        if (!$registry->isDefined($this->namespace, $this->name)) {
            throw new RuntimeException(sprintf(
                "Cannot deref var #'%s: definition has been removed from the registry",
                $this->getFullName(),
            ));
        }

        return $registry->getDefinition($this->namespace, $this->name);
    }

    public function meta(): ?PersistentMapInterface
    {
        return Registry::getInstance()->getDefinitionMetaData($this->namespace, $this->name);
    }

    /**
     * Sets the registry slot to `$f($current, ...$args)` and returns the
     * new value. Metadata attached to the definition is preserved.
     */
    public function alterRoot(callable $f, mixed ...$args): mixed
    {
        $registry = Registry::getInstance();
        if (!$registry->isDefined($this->namespace, $this->name)) {
            throw new RuntimeException(sprintf(
                "Cannot alter-var-root on #'%s: definition has been removed from the registry",
                $this->getFullName(),
            ));
        }

        $current = $registry->getDefinition($this->namespace, $this->name);
        $next = $f($current, ...$args);
        $meta = $registry->getDefinitionMetaData($this->namespace, $this->name);
        $registry->addDefinition($this->namespace, $this->name, $next, $meta);

        return $next;
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self
            && $this->namespace === $other->namespace
            && $this->name === $other->name;
    }

    public function hash(): int
    {
        return crc32($this->getFullName());
    }
}
