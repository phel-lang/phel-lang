<?php

declare(strict_types=1);

namespace Phel\Interop\Generator\Builder;

final class WrapperDestinyBuilder
{
    public function build(string $destinyDirectory, string $phelNs): string
    {
        $relativePath = str_replace(['\\', '_'], ['/', '-'], $phelNs);
        $relativePath = str_replace(' ', '/', ucwords(str_replace('/', ' ', $relativePath)));
        $relativePath = $this->dashesToCamelCase('-', $relativePath, true);

        return sprintf('%s/%s.php', $destinyDirectory, $relativePath);
    }

    private function dashesToCamelCase(string $replace, string $string, bool $capitalizeFirstCharacter = false): string
    {
        $result = str_replace(' ', '', ucwords(str_replace($replace, ' ', $string)));

        if (!$capitalizeFirstCharacter) {
            $result[0] = strtolower($result[0]);
        }

        return $result;
    }
}
