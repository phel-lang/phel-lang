<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Exceptions\CompiledCodeIsMalformedException;
use Phel\Exceptions\FileException;

interface EmitterInterface
{
    public function emitNodeAsString(AbstractNode $node): string;

    /**
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function emitNodeAndEval(AbstractNode $node): string;

    /**
     * @return mixed
     */
    public function evalCode(string $code);
}
