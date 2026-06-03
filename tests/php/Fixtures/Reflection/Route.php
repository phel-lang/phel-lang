<?php

declare(strict_types=1);

namespace PhelTest\Fixtures\Reflection;

use Attribute;

/**
 * A small route-like attribute so the Phel attribute-reflection tests can
 * read both positional and named arguments back as a Phel map.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
final class Route
{
    public function __construct(
        public string $path,
        public string $method = 'GET',
    ) {}
}
