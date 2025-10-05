<?php

declare(strict_types=1);

namespace Phel\Console\Application;

use function in_array;

final class VersionFinder
{
    public const string LATEST_VERSION = 'v0.23.0';

    private ?string $cachedVersion = null;

    public function __construct(
        private readonly string $tagCommitHash,
        private readonly string $currentCommit,
        private readonly bool $isOfficialRelease = false,
    ) {
    }

    public function getVersion(): string
    {
        if ($this->cachedVersion !== null) {
            return $this->cachedVersion;
        }

        if ($this->isOfficialRelease) {
            return $this->cachedVersion = self::LATEST_VERSION;
        }

        if ($this->currentCommit === '') {
            return $this->cachedVersion = self::LATEST_VERSION;
        }

        if ($this->tagCommitHash !== '' && $this->currentCommit === $this->tagCommitHash) {
            return $this->cachedVersion = self::LATEST_VERSION;
        }

        $hash = $this->shortCommitHash($this->currentCommit);
        if ($hash === null) {
            return $this->cachedVersion = self::LATEST_VERSION;
        }

        return $this->cachedVersion = self::LATEST_VERSION . '-beta#' . $hash;
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

        if (in_array(preg_match('/^[0-9a-f]{8,40}$/i', $reference), [0, false], true)) {
            return null;
        }

        return substr($reference, 0, 7);
    }
}
