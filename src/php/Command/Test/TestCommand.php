<?php

declare(strict_types=1);

namespace Phel\Command\Test;

use Phel\Command\Shared\NamespaceExtractorInterface;
use Phel\Command\Test\Exceptions\CannotFindAnyTestsException;
use Phel\Compiler\Emitter\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Emitter\Exceptions\FileException;
use Phel\Compiler\EvalCompilerInterface;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Runtime\RuntimeInterface;

final class TestCommand
{
    public const COMMAND_NAME = 'test';

    private string $projectRootDir;
    private RuntimeInterface $runtime;
    private NamespaceExtractorInterface $nsExtractor;
    private EvalCompilerInterface $evalCompiler;
    /** @var list<string> */
    private array $defaultDirectories;

    public function __construct(
        string $projectRootDir,
        RuntimeInterface $runtime,
        NamespaceExtractorInterface $nsExtractor,
        EvalCompilerInterface $evalCompiler,
        array $defaultDirectories
    ) {
        $this->projectRootDir = $projectRootDir;
        $this->runtime = $runtime;
        $this->nsExtractor = $nsExtractor;
        $this->evalCompiler = $evalCompiler;
        $this->defaultDirectories = $defaultDirectories;
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     * @throws CannotFindAnyTestsException
     */
    public function run(array $paths): bool
    {
        $namespaces = $this->getNamespacesFromPaths($paths);

        if (empty($namespaces)) {
            throw new CannotFindAnyTestsException();
        }

        $this->runtime->loadNs('phel\test');
        $nsString = $this->namespacesAsString($namespaces);

        return $this->evalCompiler->eval('(do (phel\test/run-tests ' . $nsString . ') (successful?))');
    }

    private function getNamespacesFromPaths(array $paths): array
    {
        if (empty($paths)) {
            return $this->nsExtractor->getNamespacesFromDirectories(
                $this->defaultDirectories,
                $this->projectRootDir
            );
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
