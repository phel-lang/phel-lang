<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Api\Transfer\NormalizedPhelFunction;

interface PhelFnNormalizerInterface
{
    /**
     * @return array<string,list<NormalizedPhelFunction>>
     */
    public function getNormalizedGroupedFunctions(): array;
}
