<?php

declare(strict_types=1);

namespace Phel\Command\Runtime;

final class ConfigNormalizer
{
    /**
     * It normalizes the structure from the loader|loader-dev config file.
     *
     * @param array<string, string|list<string>> $configLoader the loader|loader-dev from the phel-config file
     *
     * @return array<string, list<string>> [ns => [path1, path2, ...]]
     */
    public function normalize(array $configLoader, string $pathPrefix = ''): array
    {
        /** @var array<string, list<string>> $result */
        $result = [];

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
