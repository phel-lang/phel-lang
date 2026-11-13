<?php

declare(strict_types=1);

namespace Phel\Profile\Domain;

/**
 * The open call frames of one fiber, or of the main program.
 *
 * @internal
 */
final class CallStack
{
    /**
     * Positional tuple per frame: [name, enterNs, childInclusiveNs]. Keyed
     * arrays would allocate a hash slot per call; the positional layout
     * keeps `enter` to a single push on every fn invocation.
     *
     * @var list<array{0:string, 1:int, 2:int}>
     */
    public array $frames = [];
}
