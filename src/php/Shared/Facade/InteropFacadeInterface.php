<?php

declare(strict_types=1);

namespace Phel\Shared\Facade;

use Phel\Interop\Domain\ReadModel\Wrapper;

interface InteropFacadeInterface
{
    /**
     * @return list<Wrapper>
     */
    public function generateExportCode(): array;
}
