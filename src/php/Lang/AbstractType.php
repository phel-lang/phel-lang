<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\HashMap\PersistentHashMap;

/**
 * @template TSelf
 *
 * @implements MetaInterface<TSelf>
 */
abstract class AbstractType implements MetaInterface, SourceLocationInterface, EqualsInterface, HashableInterface
{
    use SourceLocationTrait;
    // private const KEYWORD_START_LOCATION = 'start-location';
    // private const KEYWORD_END_LOCATION = 'end-location';
    // private const KEYWORD_FILE = 'file';
    // private const KEYWORD_LINE = 'line';
    // private const KEYWORD_COLUMN = 'column';

    // /**
    //  * @return static
    //  */
    // public function setStartLocation(?SourceLocation $startLocation)
    // {
    //     $startLocationKeyword = TypeFactory::getInstance()->keyword(self::KEYWORD_START_LOCATION);

    //     if ($startLocation === null) {
    //         return $this->withMeta(
    //             $this->getMeta()->remove($startLocationKeyword)
    //         );
    //     }

    //     return $this->withMeta(
    //         $this->getMeta()->put($startLocation, $this->createLocationMap($startLocation))
    //     );
    // }

    // /**
    //  * @return static
    //  */
    // public function setEndLocation(?SourceLocation $endLocation)
    // {
    //     $endLocationKeyword = TypeFactory::getInstance()->keyword(self::KEYWORD_END_LOCATION);

    //     if ($endLocation === null) {
    //         return $this->withMeta(
    //             $this->getMeta()->remove($endLocationKeyword)
    //         );
    //     }

    //     return $this->withMeta(
    //         $this->getMeta()->put($endLocationKeyword, $this->createLocationMap($endLocation))
    //     );
    // }

    // public function getStartLocation(): ?SourceLocation
    // {
    //     /** @var ?SourceLocation $entry */
    //     $entry = $this->getMeta()
    //         ->find(TypeFactory::getInstance()->keyword(self::KEYWORD_START_LOCATION));

    //     return $entry;
    // }

    // public function getEndLocation(): ?SourceLocation
    // {
    //     /** @var ?SourceLocation $entry */
    //     $entry = $this->getMeta()
    //         ->find(TypeFactory::getInstance()->keyword(self::KEYWORD_END_LOCATION));

    //     return $entry;
    // }

    // /**
    //  * Copies the start and end location from $other.
    //  *
    //  * @param mixed $other The object to copy from
    //  *
    //  * @return static
    //  */
    // public function copyLocationFrom($other): self
    // {
    //     if ($other && $other instanceof SourceLocationInterface) {
    //         $this->setStartLocation($other->getStartLocation());
    //         $this->setEndLocation($other->getEndLocation());
    //     }

    //     return $this;
    // }

    // private function createLocationMap(SourceLocation $location): PersistentHashMap
    // {
    //     return TypeFactory::getInstance()->persistentHashMapFromKVs(
    //         TypeFactory::getInstance()->keyword(self::KEYWORD_FILE),
    //         $location->getFile(),
    //         TypeFactory::getInstance()->keyword(self::KEYWORD_LINE),
    //         $location->getLine(),
    //         TypeFactory::getInstance()->keyword(self::KEYWORD_COLUMN),
    //         $location->getColumn(),
    //     );
    // }
}
