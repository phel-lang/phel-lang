<?php

declare(strict_types=1);

namespace Phel\Config;

use JsonSerializable;

final class PhelExportConfig implements JsonSerializable
{
    public const FROM_DIRECTORIES = 'from-directories';

    public const NAMESPACE_PREFIX = 'namespace-prefix';

    public const TARGET_DIRECTORY = 'target-directory';

    /** @var list<string> */
    private array $fromDirectories = ['src'];

    private string $namespacePrefix = 'PhelGenerated';

    private string $targetDirectory = 'src/PhelGenerated';

    /**
     * @param list<string> $list
     */
    public function setFromDirectories(array $list): self
    {
        $this->fromDirectories = $list;

        return $this;
    }

    public function setNamespacePrefix(string $prefix): self
    {
        $this->namespacePrefix = $prefix;

        return $this;
    }

    public function setTargetDirectory(string $dir): self
    {
        $this->targetDirectory = $dir;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            self::TARGET_DIRECTORY => $this->targetDirectory,
            self::FROM_DIRECTORIES => $this->fromDirectories,
            self::NAMESPACE_PREFIX => $this->namespacePrefix,
        ];
    }
}
