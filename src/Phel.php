<?php

declare(strict_types=1);

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Registry;
use Phel\Phel as OriginalPhel;

/**
 * Public API for Phel.
 *
 * @method static void clear()
 * @method static void addDefinition(string $ns, string $name, mixed $value, ?PersistentMapInterface $metaData = null)
 * @method static bool hasDefinition(string $ns, string $name)
 * @method static mixed getDefinition(string $ns, string $name)
 * @method static null|PersistentMapInterface getDefinitionMetaData(string $ns, string $name)
 * @method static array<string, mixed> getDefinitionInNamespace(string $ns)
 * @method static list<string> getNamespaces()
 */
final class Phel extends OriginalPhel
{
    /**
     * Proxy undefined static method calls the registry instance.
     *
     * @param  list<mixed>  $arguments
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
     * @see          GlobalVarEmitter
     *
     * @noinspection PhpUnused
     */
    public static function &getDefinitionReference(string $ns, string $name): mixed
    {
        return Registry::getInstance()->getDefinitionReference($ns, $name);
    }
}
