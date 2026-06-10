<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Config;

/**
 * The resolved configuration the CLI is running with, plus its provenance.
 */
final readonly class EffectiveConfigResult
{
    /**
     * @param array<string, mixed> $values the merged config, ordered like PhelConfig::jsonSerialize()
     */
    public function __construct(
        public string $projectRoot,
        public string $configFilePath,
        public bool $configFileExists,
        public string $localConfigFilePath,
        public bool $localConfigFileExists,
        public ?string $phelDirEnv,
        public array $values,
    ) {}
}
