<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * @template TSelf
 */
interface TypeInterface extends MetaInterface, SourceLocationInterface, EqualsInterface, HashableInterface
{
}
