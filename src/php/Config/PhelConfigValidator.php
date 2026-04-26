<?php

declare(strict_types=1);

namespace Phel\Config;

use function sprintf;

final class PhelConfigValidator
{
    /**
     * @param list<string> $srcDirs
     * @param list<string> $testDirs
     *
     * @return list<string>
     */
    public function validate(array $srcDirs, array $testDirs, string $vendorDir): array
    {
        return [
            ...$this->validateRelativeDirs($srcDirs, 'Source'),
            ...$this->validateRelativeDirs($testDirs, 'Test'),
            ...$this->validateVendorDir($vendorDir),
        ];
    }

    /**
     * @param list<string> $dirs
     *
     * @return list<string>
     */
    private function validateRelativeDirs(array $dirs, string $label): array
    {
        $errors = [];
        foreach ($dirs as $dir) {
            if (str_starts_with($dir, '/')) {
                $errors[] = sprintf("%s directory '%s' should be relative, not absolute", $label, $dir);
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateVendorDir(string $vendorDir): array
    {
        if ($vendorDir !== '' && str_starts_with($vendorDir, '/')) {
            return [sprintf("Vendor directory '%s' should be relative, not absolute", $vendorDir)];
        }

        return [];
    }
}
