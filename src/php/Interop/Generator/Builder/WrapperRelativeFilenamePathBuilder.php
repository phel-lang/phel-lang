<?php

declare(strict_types=1);

namespace Phel\Interop\Generator\Builder;

final class WrapperRelativeFilenamePathBuilder
{
    public function build(string $phelNs): string
    {
        $relativePath = str_replace(' ', '/', ucwords(str_replace('\\', ' ', $phelNs)));
        $relativePath = str_replace(' ', '', ucwords(str_replace('_', ' ', $relativePath)));

        return "{$relativePath}.php";
    }
}
