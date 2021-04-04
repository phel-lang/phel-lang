<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * @template TSelf
 *
 * @implements MetaInterface<TSelf>
 */
interface TypeInterface extends MetaInterface, SourceLocationInterface, EqualsInterface, HashableInterface
{
}
