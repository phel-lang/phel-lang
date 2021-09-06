<?php

declare(strict_types=1);

namespace Phel\Config;

use JsonSerializable;
use Phel\Command\CommandConfig;
use Phel\Interop\InteropConfig;

final class ProjectConfiguration implements JsonSerializable
{
    private TestConfiguration $testConfig;

    private ExportConfiguration $exportConfig;

    public function __construct()
    {
        $this->testConfig = TestConfiguration::empty();
        $this->exportConfig = ExportConfiguration::empty();
    }

    public function setTestConfiguration(TestConfiguration $testConfig): self
    {
        $this->testConfig = $testConfig;

        return $this;
    }

    public function setExportConfiguration(ExportConfiguration $exportConfig): self
    {
        $this->exportConfig = $exportConfig;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            CommandConfig::TESTS => $this->testConfig->getDirectories(),
            InteropConfig::EXPORT => [
                InteropConfig::EXPORT_DIRECTORIES => $this->exportConfig->getDirectories(),
                InteropConfig::EXPORT_NAMESPACE_PREFIX => $this->exportConfig->getNamespacePrefix(),
                InteropConfig::EXPORT_TARGET_DIRECTORY => $this->exportConfig->getTargetDirectory(),
            ],
        ];
    }
}
