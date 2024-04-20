<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Transpiler\Domain\Emitter\OutputEmitterInterface;

trait WithOutputEmitterTrait
{
    public function __construct(private OutputEmitterInterface $outputEmitter)
    {
    }
}
