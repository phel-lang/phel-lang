<?php

declare(strict_types=1);

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Registry;
use Phel\Phel as InternalPhel;

/**
 * Public API for Phel.
 *
 * @method static void addDefinition(string $ns, string $name, mixed $value, ?PersistentMapInterface $metaData = null)
 * @method static void clear()
 * @method static bool hasDefinition(string $ns, string $name)
 * @method static mixed getDefinition(string $ns, string $name)
 * @method static PersistentMapInterface|null getDefinitionMetaData(string $ns, string $name)
 * @method static array<string,mixed> getDefinitionInNamespace(string $ns)
 * @method static list<string> getNamespaces()
 */
final class Phel extends InternalPhel
{
    /**
     * Proxy undefined static method calls to the {@see Registry} singleton.
     *
     * @param string      $name
     * @param list<mixed> $arguments
     *
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        $registry = Registry::getInstance();
        if (is_callable([$registry, $name])) {
            return $registry->$name(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Method "%s" does not exist', $name));
    }

    /**
     * Get a reference to a stored definition.
     *
     * @see GlobalVarEmitter
     * @noinspection PhpUnused
     *
     * @return mixed Reference to the stored definition.
     */
    public static function &getDefinitionReference(string $ns, string $name): mixed
    {
        return Registry::getInstance()->getDefinitionReference($ns, $name);
    }
}
