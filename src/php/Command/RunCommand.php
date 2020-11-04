<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Common\NamespaceExtractorInterface;
use Phel\RuntimeInterface;
use RuntimeException;

final class RunCommand
{
    public const NAME = 'run';

    private RuntimeInterface $runtime;
    private NamespaceExtractorInterface $namespaceExtractor;

    public function __construct(
        RuntimeInterface $runtime,
        NamespaceExtractorInterface $namespaceExtractor
    ) {
        $this->runtime = $runtime;
        $this->namespaceExtractor = $namespaceExtractor;
    }

    public function run(string $fileOrPath): void
    {
        $ns = file_exists($fileOrPath)
            ? $this->namespaceExtractor->getNamespaceFromFile($fileOrPath)
            : $fileOrPath;

        $result = $this->runtime->loadNs($ns);

        if (!$result) {
            throw new RuntimeException('Cannot load namespace: ' . $ns);
        }
    }
}
