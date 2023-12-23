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
     * @param list<string> $directories
     */
    public function setDirectories(array $directories): self
    {
        $this->directories = $directories;

        return $this;
    }

    public function getNamespacePrefix(): string
    {
        return $this->namespacePrefix;
    }

    public function setNamespacePrefix(string $namespacePrefix): self
    {
        $this->namespacePrefix = $namespacePrefix;

        return $this;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }

    public function setTargetDirectory(string $targetDirectory): self
    {
        $this->targetDirectory = $targetDirectory;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            InteropConfig::EXPORT_TARGET_DIRECTORY => $this->getTargetDirectory(),
            InteropConfig::EXPORT_DIRECTORIES => $this->getDirectories(),
            InteropConfig::EXPORT_NAMESPACE_PREFIX => $this->getNamespacePrefix(),
        ];
    }
}
