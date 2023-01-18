<?php

declare(strict_types=1);

namespace Phel\Internal\Domain;

use Phel\Internal\Transfer\NormalizedPhelFunction;

interface PhelFnNormalizerInterface
{
    /**
     * @return array<string,list<NormalizedPhelFunction>>
     */
    public function getNormalizedGroupedFunctions(): array;
}
