<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\HashMap\PersistentHashMap;
use Phel\Printer\Printer;

/**
 * @template TSelf
 *
 * @implements TypeInterface<TSelf>
 */
abstract class AbstractType implements TypeInterface
{
    private const KEYWORD_START_LOCATION = 'start-location';
    private const KEYWORD_END_LOCATION = 'end-location';
    private const KEYWORD_FILE = 'file';
    private const KEYWORD_LINE = 'line';
    private const KEYWORD_COLUMN = 'column';

    /**
     * @return static
     */
    public function setStartLocation(?SourceLocation $startLocation)
    {
        $startLocationKeyword = TypeFactory::getInstance()->keyword(self::KEYWORD_START_LOCATION);

        if ($startLocation === null) {
            return $this;
        }

        $meta = $this->getMeta();
        if ($meta === null) {
            $meta = TypeFactory::getInstance()->emptyPersistentHashMap();
        }

        return $this->withMeta(
            $meta->put($startLocationKeyword, $this->createLocationMap($startLocation))
        );
    }

    /**
     * @return static
     */
    public function setEndLocation(?SourceLocation $endLocation)
    {
        $endLocationKeyword = TypeFactory::getInstance()->keyword(self::KEYWORD_END_LOCATION);

        if ($endLocation === null) {
            return $this;
        }

        $meta = $this->getMeta();
        if ($meta === null) {
            $meta = TypeFactory::getInstance()->emptyPersistentHashMap();
        }

        return $this->withMeta(
            $meta->put($endLocationKeyword, $this->createLocationMap($endLocation))
        );
    }

    public function getStartLocation(): ?SourceLocation
    {
        $meta = $this->getMeta();
        $entry = null;
        if ($meta) {
            /** @var ?PersistentHashMap $entry */
            $entry = $meta->find(TypeFactory::getInstance()->keyword(self::KEYWORD_START_LOCATION));
        }

        return $entry ? $this->createLocationFromMap($entry) : null;
    }

    public function getEndLocation(): ?SourceLocation
    {
        $meta = $this->getMeta();
        $entry = null;
        if ($meta) {
            /** @var ?PersistentHashMap $entry */
            $entry = $meta->find(TypeFactory::getInstance()->keyword(self::KEYWORD_END_LOCATION));
        }

        return $entry ? $this->createLocationFromMap($entry) : null;
    }

    /**
     * Copies the start and end location from $other.
     *
     * @param mixed $other The object to copy from
     *
     * @return static
     */
    public function copyLocationFrom($other): self
    {
        if ($other && $other instanceof SourceLocationInterface) {
            $this->setStartLocation($other->getStartLocation());
            $this->setEndLocation($other->getEndLocation());
        }

        return $this;
    }

    private function createLocationMap(SourceLocation $location): PersistentHashMap
    {
        return TypeFactory::getInstance()->persistentHashMapFromKVs(
            TypeFactory::getInstance()->keyword(self::KEYWORD_FILE),
            $location->getFile(),
            TypeFactory::getInstance()->keyword(self::KEYWORD_LINE),
            $location->getLine(),
            TypeFactory::getInstance()->keyword(self::KEYWORD_COLUMN),
            $location->getColumn(),
        );
    }

    private function createLocationFromMap(PersistentHashMap $map): SourceLocation
    {
        return new SourceLocation(
            $map->find(TypeFactory::getInstance()->keyword(self::KEYWORD_FILE)) ?? 'source',
            $map->find(TypeFactory::getInstance()->keyword(self::KEYWORD_LINE)) ?? 0,
            $map->find(TypeFactory::getInstance()->keyword(self::KEYWORD_COLUMN)) ?? 0,
        );
    }

    public function __toString()
    {
        return Printer::readable()->print($this);
    }
}
