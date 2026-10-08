<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Config;

/**
 * The resolved configuration the CLI is running with, plus its provenance.
 *
 * @internal
 */
final readonly class EffectiveConfigResult
{
    /**
     * @param array<string, mixed> $configuredValues the merged config files, keyed and ordered like PhelConfig::jsonSerialize()
     * @param array<string, mixed> $values           the same keys after env vars and flags, as a command runs with them
     */
    public function __construct(
        public string $projectRoot,
        public string $configFilePath,
        public bool $configFileExists,
        public string $localConfigFilePath,
        public bool $localConfigFileExists,
        public ?string $phelDirEnv,
        public array $configuredValues,
        public array $values,
    ) {}
}
