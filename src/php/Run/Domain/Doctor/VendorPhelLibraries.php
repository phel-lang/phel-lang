<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Doctor;

use function array_unique;
use function count;
use function explode;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Groups Phel source dirs under the vendor dir by Composer package.
 *
 * @internal
 */
final class VendorPhelLibraries
{
    private const string PHEL_PACKAGE = 'phel-lang/phel-lang';

    /**
     * @param list<string> $phelDirectories   every Phel source dir in use
     * @param string       $vendorDir         the vendor dir as configured, which is how vendor source dirs are built
     * @param string       $resolvedVendorDir the same dir with symlinks resolved, as project dirs are
     *
     * @return array<string, list<string>> package name => its source dirs, prefixed with $vendorName
     */
    public static function group(array $phelDirectories, string $vendorDir, string $resolvedVendorDir, string $vendorName): array
    {
        $prefixes = array_unique([self::slashes($vendorDir) . '/', self::slashes($resolvedVendorDir) . '/']);

        $libraries = [];
        foreach ($phelDirectories as $dir) {
            $dir = self::slashes($dir);
            foreach ($prefixes as $prefix) {
                if (!str_starts_with($dir, $prefix)) {
                    continue;
                }

                $relative = substr($dir, strlen($prefix));
                $parts = explode('/', $relative, 3);
                $name = count($parts) >= 2 ? $parts[0] . '/' . $parts[1] : null;
                if ($name !== null && $name !== self::PHEL_PACKAGE) {
                    $libraries[$name][] = $vendorName . '/' . $relative;
                }

                break;
            }
        }

        return $libraries;
    }

    private static function slashes(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
