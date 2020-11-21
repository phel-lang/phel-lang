<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Emitter\OutputEmitter;

trait WithOutputEmitter
{
    private OutputEmitter $outputEmitter;

    public function __construct(OutputEmitter $emitter)
    {
        $this->outputEmitter = $emitter;
    }
}
