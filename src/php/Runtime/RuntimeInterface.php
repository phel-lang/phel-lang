<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;

interface RuntimeInterface
{
    public function getEnv(): GlobalEnvironmentInterface;

    /**
     * @param string $namespacePrefix
     * @param array<int, string> $path
     */
    public function addPath(string $namespacePrefix, array $path): void;

    /**
     * @return list<string>
     */
    public function getSourceDirectories(): array;

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     *
     * @return bool true if the namespace was successfully loaded; false otherwise
     *
     * @deprecated this function only exists because the phel composer plugin is using it
     */
    public function loadNs(string $ns): bool;
}
