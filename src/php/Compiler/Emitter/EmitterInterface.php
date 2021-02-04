<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Emitter\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Emitter\Exceptions\FileException;

interface EmitterInterface
{
    public function emitNodeAsString(AbstractNode $node): string;

    /**
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function emitNodeAndEval(AbstractNode $node): string;

    /**
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     *
     * @return mixed
     */
    public function evalCode(string $code);
}
