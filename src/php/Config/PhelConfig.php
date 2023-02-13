<?php

declare(strict_types=1);

namespace Phel\Config;

final class PhelConfig
{
    private array $srcDirs = ['src/phel'];
    private array $testDirs = ['tests/phel'];
    private string $vendorDir = 'vendor';
    private string $outDir = 'out';
    private PhelExportConfig $export;
    private array $ignoreWhenBuilding = ['src/phel/local.phel'];
    private bool $keepGeneratedTempFiles = false;
    private array $formatDirs = ['src', 'tests'];

    public function __construct()
    {
        $this->export = new PhelExportConfig();
    }

    public function getSrcDirs(): array
    {
        return $this->srcDirs;
    }

    public function setSrcDirs(array $srcDirs): self
    {
        $this->srcDirs = $srcDirs;

        return $this;
    }

    public function getTestDirs(): array
    {
        return $this->testDirs;
    }

    public function setTestDirs(array $testDirs): self
    {
        $this->testDirs = $testDirs;

        return $this;
    }

    public function getVendorDir(): string
    {
        return $this->vendorDir;
    }

    public function setVendorDir(string $vendorDir): self
    {
        $this->vendorDir = $vendorDir;

        return $this;
    }

    public function getExport(): PhelExportConfig
    {
        return $this->export;
    }

    public function setExport(PhelExportConfig $export): self
    {
        $this->export = $export;

        return $this;
    }

    public function getOutDir(): string
    {
        return $this->outDir;
    }

    public function setOutDir(string $outDir): self
    {
        $this->outDir = $outDir;

        return $this;
    }

    public function getIgnoreWhenBuilding(): array
    {
        return $this->ignoreWhenBuilding;
    }

    public function setIgnoreWhenBuilding(array $ignoreWhenBuilding): self
    {
        $this->ignoreWhenBuilding = $ignoreWhenBuilding;

        return $this;
    }

    public function isKeepGeneratedTempFiles(): bool
    {
        return $this->keepGeneratedTempFiles;
    }

    public function setKeepGeneratedTempFiles(bool $keepGeneratedTempFiles): self
    {
        $this->keepGeneratedTempFiles = $keepGeneratedTempFiles;

        return $this;
    }

    public function getFormatDirs(): array
    {
        return $this->formatDirs;
    }

    public function setFormatDirs(array $formatDirs): self
    {
        $this->formatDirs = $formatDirs;

        return $this;
    }
}
