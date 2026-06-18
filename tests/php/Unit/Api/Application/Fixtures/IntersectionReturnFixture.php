<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application\Fixtures;

use Stringable;

/**
 * Fixture whose method returns a `class&interface` intersection, so the interop
 * reflector must pick the reflectable class member to keep walking a chain.
 *
 * The intersection is declared here (not on ChainFixture) so it can spell out
 * the class name: PHP forbids `self` inside an intersection type.
 */
final class IntersectionReturnFixture
{
    public function andStringable(): ChainFixture&Stringable
    {
        return ChainFixture::make()->withName('x');
    }
}
