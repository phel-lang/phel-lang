<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Interop\ReadModel\Wrapper;

interface InteropFacadeInterface
{
    public function removeDestinationDir(): void;

    public function createFileFromWrapper(Wrapper $wrapper): void;

    /**
     * @return list<Wrapper>
     */
    public function generateWrappers(): array;
}
