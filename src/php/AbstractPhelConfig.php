<?php

declare(strict_types=1);

namespace Phel;

use RuntimeException;

abstract class AbstractPhelConfig
{
    private array $config;

    public function __construct(string $rootDir)
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

        $this->config = $composerData['extra']['phel'];
    }

    /**
     * @return mixed|null
     */
    public function get(string $key)
    {
        return $this->config[$key] ?? null;
    }
}
