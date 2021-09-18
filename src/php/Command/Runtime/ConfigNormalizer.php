<?php

declare(strict_types=1);

namespace Phel\Command\Runtime;

final class ConfigNormalizer
{
    /**
     * @param array<string, list<string>> $result [ns => [path1, path2, ...]]
     *
     * @return array<string, list<string>>
     */
    public function normalize(array $result, array $configLoader, string $pathPrefix = ''): array
    {
        foreach ($configLoader as $ns => $pathList) {
            if (!isset($result[$ns])) {
                $result[$ns] = [];
            }

            if (is_string($pathList)) {
                $pathList = [$pathList];
            }

            foreach ($pathList as $path) {
                $result[$ns][] = $pathPrefix . '/' . $path;
            }
        }

        return $result;
    }
}
