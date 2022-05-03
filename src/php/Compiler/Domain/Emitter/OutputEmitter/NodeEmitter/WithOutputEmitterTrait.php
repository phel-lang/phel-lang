<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

trait WithOutputEmitterTrait
{
    private OutputEmitterInterface $outputEmitter;

    public function __construct(OutputEmitterInterface $emitter)
    {
        $this->outputEmitter = $emitter;
    }
}
