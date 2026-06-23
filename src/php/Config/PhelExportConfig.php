<?php

declare(strict_types=1);

namespace Phel\Config;

use JsonSerializable;

/**
 * Immutable export configuration nested under {@see PhelConfig} (`export` key).
 *
 * Canonical API: the `with*()` setters.
 */
final readonly class PhelExportConfig implements JsonSerializable
{
    public const string FROM_DIRECTORIES = 'from-directories';

    public const string NAMESPACE_PREFIX = 'namespace-prefix';

    public const string TARGET_DIRECTORY = 'target-directory';

    /**
     * @param list<string> $fromDirectories
     */
    public function __construct(
        public array $fromDirectories = ['src'],
        public string $namespacePrefix = 'PhelGenerated',
        public string $targetDirectory = 'src/PhelGenerated',
    ) {}

    /**
     * @param list<string> $list
     */
    public function withFromDirectories(array $list): self
    {
        return new self($list, $this->namespacePrefix, $this->targetDirectory);
    }

    public function withNamespacePrefix(string $prefix): self
    {
        return new self($this->fromDirectories, $prefix, $this->targetDirectory);
    }

    public function withTargetDirectory(string $dir): self
    {
        return new self($this->fromDirectories, $this->namespacePrefix, $dir);
    }

    /**
     * Serializes to the Gacela wire format consumed by `AbstractConfig::get()`.
     * The keys come from the `*_DIRECTORY`/`*_DIRECTORIES`/`*_PREFIX` constants
     * and must never be renamed or recased without updating that contract.
     *
     * @return array{target-directory: string, from-directories: list<string>, namespace-prefix: string}
     */
    public function jsonSerialize(): array
    {
        return [
            self::TARGET_DIRECTORY => $this->targetDirectory,
            self::FROM_DIRECTORIES => $this->fromDirectories,
            self::NAMESPACE_PREFIX => $this->namespacePrefix,
        ];
    }
}
