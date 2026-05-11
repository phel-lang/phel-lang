<?php

declare(strict_types=1);

namespace Phel\Shared;

interface MungeInterface
{
    public function encode(string $str): string;

    public function encodePhpNs(string $str): string;

    public function encodeRegistryKey(string $str): string;

    public function decodeNs(string $str): string;
}
