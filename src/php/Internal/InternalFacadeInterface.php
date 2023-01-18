<?php

declare(strict_types=1);

namespace Phel\Internal;

use Phel\Internal\Transfer\NormalizedPhelFunction;

interface InternalFacadeInterface
{
    /**
     * @return array<string,list<NormalizedPhelFunction>>
     */
    public function getNormalizedGroupedFunctions(): array;
}
