<?php

declare(strict_types=1);

namespace Phel;

use RuntimeException;

final class PhelConfig implements PhelConfigInterface
{
    private array $config;

    public static function createFromRootDir(string $rootDir): self
    {
        $composerContent = file_get_contents($rootDir . 'composer.json');
        if (!$composerContent) {
            throw new RuntimeException('Cannot read composer.json in: ' . $rootDir);
        }

        $composerData = \json_decode($composerContent, true);
        if (!$composerData) {
            throw new RuntimeException('Cannot read composer.json in: ' . $rootDir);
        }

        if (!isset($composerData['extra']['phel'])) {
            throw new RuntimeException('No Phel configuration found in composer.json');
        }

        return new self($composerData['extra']['phel']);
    }

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return mixed|null
     */
    public function get(string $key)
    {
        return $this->config[$key] ?? null;
    }
}
