<?php

declare(strict_types=1);

namespace Phel\Commands;

use Phel\Commands\Utils\NamespaceExtractorInterface;
use Phel\Compiler\EvalCompiler;
use Phel\RuntimeInterface;
use RuntimeException;

final class TestCommand
{
    public const NAME = 'test';

    private string $currentDir;
    private RuntimeInterface $runtime;
    private NamespaceExtractorInterface $nsExtractor;

    public function __construct(
        string $currentDir,
        RuntimeInterface $runtime,
        NamespaceExtractorInterface $nsExtractor
    ) {
        $this->currentDir = $currentDir;
        $this->runtime = $runtime;
        $this->nsExtractor = $nsExtractor;
    }

    public function run(array $paths): bool
    {
        $namespaces = $this->getNamespacesFromPaths($paths);

        if (empty($namespaces)) {
            throw new RuntimeException('Can not find any tests');
        }

        $this->runtime->loadNs('phel\test');

        $compiler = new EvalCompiler($this->runtime->getEnv());
        $nsString = $this->namespacesAsString($namespaces);

        return $compiler->eval('(do (phel\test/run-tests ' . $nsString . ') (successful?))');
    }

    private function getNamespacesFromPaths(array $paths): array
    {
        if (empty($paths)) {
            return $this->nsExtractor->getNamespacesFromConfig($this->currentDir);
        }

        return array_map(
            fn (string $filename): string => $this->nsExtractor->getNamespaceFromFile($filename),
            $paths
        );
    }

    private function namespacesAsString(array $namespaces): string
    {
        return implode(' ', array_map(
            static fn (string $ns): string => "'" . $ns,
            $namespaces
        ));
    }
}
