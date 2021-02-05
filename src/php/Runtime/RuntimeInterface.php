<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Emitter\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Emitter\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;

interface RuntimeInterface
{
    public function getEnv(): GlobalEnvironmentInterface;

    /**
     * @param string $namespacePrefix
     * @param string[] $path
     */
    public function addPath(string $namespacePrefix, array $path): void;

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     *
     * @return bool true if the namespace was successfully loaded; false otherwise
     */
    public function loadNs(string $ns): bool;

    public function loadFileIntoNamespace(string $ns, string $file): void;
}
