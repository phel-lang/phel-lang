<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Emitter\OutputEmitter;

trait WithOutputEmitter
{
    private OutputEmitter $outputEmitter;

    public function __construct(OutputEmitter $emitter)
    {
        $this->outputEmitter = $emitter;
    }
}
