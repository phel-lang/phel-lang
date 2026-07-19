<?php

declare(strict_types=1);

namespace Phel\Shared\Facade;

use Phel\Shared\Interop\Wrapper;

interface InteropFacadeInterface
{
    /**
     * @return list<Wrapper>
     */
    public function generateExportCode(): array;
}
