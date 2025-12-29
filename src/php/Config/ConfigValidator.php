<?php

declare(strict_types=1);

namespace Phel\Config;

use function sprintf;

final class ConfigValidator
{
    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> */
    private array $errors = [];

    /**
     * Validate configuration and return warnings/errors.
     * Does not throw - caller decides how to handle issues.
     */
    public function validate(string $projectRootDir, PhelConfig $config): self
    {
        $this->warnings = [];
        $this->errors = [];

        $this->validateDirectories($projectRootDir, $config);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    /**
     * Output validation results to STDERR.
     */
    public function outputResults(): void
    {
        foreach ($this->warnings as $warning) {
            fwrite(STDERR, "\033[33mWarning:\033[0m " . $warning . "\n");
        }

        foreach ($this->errors as $error) {
            fwrite(STDERR, "\033[31mError:\033[0m " . $error . "\n");
        }
    }

    private function validateDirectories(string $projectRootDir, PhelConfig $config): void
    {
        $serialized = $config->jsonSerialize();

        // Check source directories
        $srcDirs = $serialized[PhelConfig::SRC_DIRS] ?? [];
        $foundSrcDir = false;
        foreach ($srcDirs as $srcDir) {
            $fullPath = $this->resolvePath($projectRootDir, $srcDir);
            if (is_dir($fullPath)) {
                $foundSrcDir = true;
            }
        }

        if (!$foundSrcDir && $srcDirs !== []) {
            $this->addDirectoryWarning($projectRootDir, $srcDirs, 'source');
        }

        // Check test directories (only warn, tests are optional)
        $testDirs = $serialized[PhelConfig::TEST_DIRS] ?? [];
        $foundTestDir = false;
        foreach ($testDirs as $testDir) {
            $fullPath = $this->resolvePath($projectRootDir, $testDir);
            if (is_dir($fullPath)) {
                $foundTestDir = true;
            }
        }

        // Only warn about missing test dirs if explicitly configured (not defaults)
        // This is a soft warning since tests are optional

        // Check vendor directory
        $vendorDir = $serialized[PhelConfig::VENDOR_DIR] ?? 'vendor';
        if ($vendorDir !== '' && !is_dir($projectRootDir . '/' . $vendorDir)) {
            $this->warnings[] = sprintf(
                "Vendor directory '%s' not found. Run 'composer install' first.",
                $vendorDir,
            );
        }
    }

    private function addDirectoryWarning(string $projectRootDir, array $dirs, string $type): void
    {
        $dirsStr = implode(', ', array_map(static fn (string $d): string => sprintf("'%s'", $d), $dirs));

        // Check what directories actually exist and suggest alternatives
        $suggestions = [];
        if ($type === 'source') {
            if (is_dir($projectRootDir . '/src/phel')) {
                $suggestions[] = 'src/phel';
            } elseif (is_dir($projectRootDir . '/src')) {
                $suggestions[] = 'src';
            }
        }

        $message = sprintf(
            'Configured %s directories (%s) not found.',
            $type,
            $dirsStr,
        );

        if ($suggestions !== []) {
            $message .= sprintf(' Did you mean: %s?', implode(' or ', $suggestions));
        } else {
            $message .= ' Create the directory or update phel-config.php.';
        }

        $this->warnings[] = $message;
    }

    private function resolvePath(string $projectRootDir, string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $projectRootDir . '/' . $path;
    }
}
