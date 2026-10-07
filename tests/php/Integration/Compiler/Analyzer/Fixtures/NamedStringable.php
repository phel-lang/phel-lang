<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer\Fixtures;

use Stringable;

interface NamedStringable extends Stringable
{
    public function name(): string;
}
