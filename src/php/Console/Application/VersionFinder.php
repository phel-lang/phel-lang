<?php

declare(strict_types=1);

namespace Phel\Console\Application;

use function in_array;

final class VersionFinder
{
    public const string LATEST_VERSION = 'v0.20.0';

    /** Cache computed version */
    private ?string $cachedVersion = null;

    /**
     * @param array{pretty_version?:string, reference?:string} $rootPackage
     */
    public function __construct(
        private readonly array $rootPackage,
    ) {
    }

    public function getVersion(): string
    {
        if ($this->cachedVersion !== null) {
            return $this->cachedVersion;
        }

        $version = self::LATEST_VERSION;

        $tag  = $this->rootPackage['pretty_version'] ?? '';
        $hash = $this->shortCommitHash($this->rootPackage['reference'] ?? '');

        if ($tag !== self::LATEST_VERSION && $hash !== null) {
            $version .= '-beta#' . $hash;
        }

        return $this->cachedVersion = $version;
    }

    /**
     * Return a 7-char short hash if $reference looks like a valid git SHA, otherwise null.
     */
    private function shortCommitHash(string $reference): ?string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        if (in_array(preg_match('/^[0-9a-f]{7,40}$/i', $reference), [0, false], true)) {
            return null;
        }

        return substr($reference, 0, 7);
    }
}
