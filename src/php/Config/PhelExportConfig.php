<?php

declare(strict_types=1);

namespace Phel\Config;

use JsonSerializable;
use Phel\Interop\InteropConfig;

final class PhelExportConfig implements JsonSerializable
{
    /** @var list<string> */
    private array $directories = ['src/phel'];

    private string $namespacePrefix = 'PhelGenerated';

    private string $targetDirectory = 'src/PhelGenerated';

    public function getDirectories(): array
    {
        return $this->directories;
    }

    /**
     * @param list<string> $list
     */
    public function setDirectories(array $list): self
    {
        $this->directories = $list;

        return $this;
    }

    public function getNamespacePrefix(): string
    {
        return $this->namespacePrefix;
    }

    public function setNamespacePrefix(string $prefix): self
    {
        $this->namespacePrefix = $prefix;

        return $this;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }

    public function setTargetDirectory(string $dir): self
    {
        $this->targetDirectory = $dir;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            InteropConfig::EXPORT_TARGET_DIRECTORY => $this->targetDirectory,
            InteropConfig::EXPORT_DIRECTORIES => $this->directories,
            InteropConfig::EXPORT_NAMESPACE_PREFIX => $this->namespacePrefix,
        ];
    }
}
