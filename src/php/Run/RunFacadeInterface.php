<?php

declare(strict_types=1);

namespace Phel\Run;

use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

interface RunFacadeInterface
{
    public function runNamespace(string $namespace): void;

    /**
     * @return mixed The result of the executed code
     */
    public function eval(string $phelCode, CompileOptions $compileOptions): mixed;

    /**
     * @return list<string>
     */
    public function getAllPhelDirectories(): array;

    /**
     * @param list<string> $directories
     * @param list<string> $ns
     *
     * @return list<NamespaceInformation>
     */
    public function getDependenciesForNamespace(array $directories, array $ns): array;

    public function evalFile(NamespaceInformation $info): void;

    /**
     * @param list<string> $paths
     *
     * @return list<NamespaceInformation>
     */
    public function getDependenciesFromPaths(array $paths): array;

    public function getNamespaceFromFile(string $fileOrPath): NamespaceInformation;

    public function writeLocatedException(OutputInterface $output, CompilerException $e): void;

    public function writeStackTrace(OutputInterface $output, Throwable $e): void;
}
