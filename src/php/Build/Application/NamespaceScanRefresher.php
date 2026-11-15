<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;

/**
 * @internal
 */
final readonly class NamespaceScanRefresher
{
    public function __construct(private NamespaceExtractorInterface $extractor) {}

    public function refresh(): void
    {
        if ($this->extractor instanceof CachedNamespaceExtractor) {
            $this->extractor->resetDirectoryScans();
        }

        FileEvaluator::resetState();
    }
}
