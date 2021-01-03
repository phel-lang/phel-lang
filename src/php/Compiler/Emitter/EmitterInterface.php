<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Ast\AbstractNode;

interface EmitterInterface
{
    public function emitNodeAndEval(AbstractNode $node): string;

    public function emitNodeAsString(AbstractNode $node): string;

    /**
     * @return mixed
     */
    public function evalCode(string $code);
}
