<?php

declare(strict_types=1);

namespace Phel\Commands;

use Phel\Commands\Utils\NamespaceExtractorInterface;
use Phel\Compiler\EvalCompiler;
use Phel\RuntimeInterface;

final class TestCommand
{
    public const NAME = 'test';

    private string $currentDir;
    private RuntimeInterface $runtime;
    private NamespaceExtractorInterface $namespaceExtractor;

    public function __construct(
        string $currentDir,
        RuntimeInterface $runtime,
        NamespaceExtractorInterface $namespaceExtractor
    ) {
        $this->currentDir = $currentDir;
        $this->runtime = $runtime;
        $this->namespaceExtractor = $namespaceExtractor;
    }

    public function run(array $paths): bool
    {
        $namespaces = empty($paths)
            ? $this->namespaceExtractor->getNamespacesFromConfig($this->currentDir)
            : array_map(fn ($filename) => $this->namespaceExtractor->getNamespaceFromFile($filename), $paths);

        if (empty($namespaces)) {
            throw new \RuntimeException('Can not find any tests');
        }

        $this->runtime->loadNs('phel\test');

        $compiler = new EvalCompiler($this->runtime->getEnv());
        $nsString = implode(' ', array_map(fn (string $x) => "'" . $x, $namespaces));

        return $compiler->eval('(do (phel\test/run-tests ' . $nsString . ') (successful?))');
    }
}
