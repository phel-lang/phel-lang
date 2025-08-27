<?php

declare(strict_types=1);

namespace Phel\Console\Application;

final class VersionFinder
{
    public const string LATEST_VERSION = 'v0.20.0';

    private ?string $version = null;

    /**
     * @param array{pretty_version?:string, reference?: string} $rootPackage
     */
    public function __construct(
        private readonly array $rootPackage,
    ) {
    }

    public function getVersion(): string
    {
        if ($this->version !== null) {
            return $this->version;
        }

        $version = self::LATEST_VERSION;

        $tag = $this->rootPackage['pretty_version'] ?? '';
        $hash = substr($this->rootPackage['reference'] ?? '', 0, 7);

        if ($tag !== self::LATEST_VERSION && $hash !== '') {
            $version .= '-beta#' . $hash;
        }

        return $this->version = $version;
    }
}
