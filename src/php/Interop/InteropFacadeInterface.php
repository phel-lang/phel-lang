<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Interop\Domain\ReadModel\Wrapper;

interface InteropFacadeInterface
{
    /**
     * @return list<Wrapper>
     */
    public function generateExportCode(): array;
}
