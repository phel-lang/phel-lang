<?php

declare(strict_types=1);

namespace Phel\Interop\Generator\Builder;

final class WrapperRelativeFilenamePathBuilder
{
    public function build(string $phelNs): string
    {
        $relativePath = str_replace(['\\', '_'], ['/', '-'], $phelNs);
        $relativePath = str_replace(' ', '/', ucwords(str_replace('/', ' ', $relativePath)));
        $relativePath = $this->dashesToCamelCase('-', $relativePath);

        return "{$relativePath}.php";
    }

    private function dashesToCamelCase(string $replace, string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace($replace, ' ', $string)));
    }
}
