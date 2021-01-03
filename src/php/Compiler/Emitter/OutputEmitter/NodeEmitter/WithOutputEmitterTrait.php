<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Emitter\OutputEmitterInterface;

trait WithOutputEmitterTrait
{
    private OutputEmitterInterface $outputEmitter;

    public function __construct(OutputEmitterInterface $emitter)
    {
        $this->outputEmitter = $emitter;
    }
}
