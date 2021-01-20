<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter;

interface MungeInterface
{
    public function encode(string $str): string;

    public function encodeNs(string $str): string;

    public function decodeNs(string $str): string;
}
