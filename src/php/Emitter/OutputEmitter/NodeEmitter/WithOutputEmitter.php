<?php

declare(strict_types=1);

namespace Phel\Emitter\OutputEmitter\NodeEmitter;

use Phel\Emitter\OutputEmitter;

trait WithOutputEmitter
{
    private OutputEmitter $outputEmitter;

    public function __construct(OutputEmitter $emitter)
    {
        $this->outputEmitter = $emitter;
    }
}
