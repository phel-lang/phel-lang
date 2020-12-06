<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Ast\Node;

interface EmitterInterface
{
    public function emitNodeAndEval(Node $node): string;

    public function emitNodeAsString(Node $node): string;

    /**
     * @return mixed
     */
    public function evalCode(string $code);
}
