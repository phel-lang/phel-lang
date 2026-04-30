<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

interface MungeInterface
{
    public function encode(string $str): string;

    public function encodePhpNs(string $str): string;

    public function encodeRegistryKey(string $str): string;

    public function decodeNs(string $str): string;
}
