<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Shared\NamespaceExtractorInterface;
use Phel\Compiler\EvalCompilerInterface;
use Phel\RuntimeInterface;
use RuntimeException;

final class TestCommand
{
    public const COMMAND_NAME = 'test';

    private string $currentDir;
    private RuntimeInterface $runtime;
    private NamespaceExtractorInterface $nsExtractor;
    private EvalCompilerInterface $evalCompiler;

    public function __construct(
        string $currentDir,
        RuntimeInterface $runtime,
        NamespaceExtractorInterface $nsExtractor,
        EvalCompilerInterface $evalCompiler
    ) {
        $this->currentDir = $currentDir;
        $this->runtime = $runtime;
        $this->nsExtractor = $nsExtractor;
        $this->evalCompiler = $evalCompiler;
    }

    public function run(array $paths): bool
    {
        $namespaces = $this->getNamespacesFromPaths($paths);

        if (empty($namespaces)) {
            throw new RuntimeException('Cannot find any tests');
        }

        $this->runtime->loadNs('phel\test');
        $nsString = $this->namespacesAsString($namespaces);

        return $this->evalCompiler->eval('(do (phel\test/run-tests ' . $nsString . ') (successful?))');
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
