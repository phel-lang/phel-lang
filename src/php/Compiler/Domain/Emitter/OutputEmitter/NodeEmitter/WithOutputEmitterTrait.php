<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

trait WithOutputEmitterTrait
{
    public function __construct(private OutputEmitterInterface $outputEmitter)
    {
    }
}
