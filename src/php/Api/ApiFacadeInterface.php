<?php

declare(strict_types=1);

namespace Phel\Api;

use Phel\Api\Transfer\NormalizedPhelFunction;

interface ApiFacadeInterface
{
    /**
     * @return array<string,list<NormalizedPhelFunction>>
     */
    public function getNormalizedGroupedFunctions(): array;
}
