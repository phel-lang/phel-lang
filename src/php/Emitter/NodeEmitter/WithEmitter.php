<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Emitter;

trait WithEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }
}
