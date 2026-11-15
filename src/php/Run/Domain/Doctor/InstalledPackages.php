<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Doctor;

use function is_array;
use function is_string;
use function json_decode;

/**
 * Reads Composer's `vendor/composer/installed.json`: an object with `packages`
 * (Composer 2) or a bare list (Composer 1).
 *
 * @internal
 */
final class InstalledPackages
{
    /**
     * @return array<string, InstalledPackage> package name => package
     */
    public static function fromJson(string $json): array
    {
        $data = json_decode($json, true);
        $packages = is_array($data) && is_array($data['packages'] ?? null) ? $data['packages'] : $data;
        if (!is_array($packages)) {
            return [];
        }

        $result = [];
        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }

            $name = $package['name'] ?? null;
            $version = $package['version'] ?? null;
            if (is_string($name) && is_string($version)) {
                $result[$name] = new InstalledPackage($version, self::reference($package));
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $package
     */
    private static function reference(array $package): string
    {
        foreach (['source', 'dist'] as $origin) {
            $reference = is_array($package[$origin] ?? null) ? ($package[$origin]['reference'] ?? null) : null;
            if (is_string($reference) && $reference !== '') {
                return $reference;
            }
        }

        return '';
    }
}
