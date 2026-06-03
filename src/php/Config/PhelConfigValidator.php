<?php

declare(strict_types=1);

namespace Phel\Config;

use function sprintf;

final class PhelConfigValidator
{
    /**
     * @return list<string>
     */
    public function validate(PhelConfig $config): array
    {
        return [
            ...$this->validateRelativeDirs($config->srcDirs, 'Source'),
            ...$this->validateRelativeDirs($config->testDirs, 'Test'),
            ...$this->validateVendorDir($config->vendorDir),
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
