<?php

declare(strict_types=1);

namespace Phel\Config;

use Deprecated;
use JsonSerializable;

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

    /**
     * @param list<string> $list
     */
    #[Deprecated(message: 'since 0.37, use withFromDirectories()')]
    public function setFromDirectories(array $list): self
    {
        return $this->withFromDirectories($list);
    }

    public function withNamespacePrefix(string $prefix): self
    {
        return new self($this->fromDirectories, $prefix, $this->targetDirectory);
    }

    #[Deprecated(message: 'since 0.37, use withNamespacePrefix()')]
    public function setNamespacePrefix(string $prefix): self
    {
        return $this->withNamespacePrefix($prefix);
    }

    public function withTargetDirectory(string $dir): self
    {
        return new self($this->fromDirectories, $this->namespacePrefix, $dir);
    }

    #[Deprecated(message: 'since 0.37, use withTargetDirectory()')]
    public function setTargetDirectory(string $dir): self
    {
        return $this->withTargetDirectory($dir);
    }

    /**
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
