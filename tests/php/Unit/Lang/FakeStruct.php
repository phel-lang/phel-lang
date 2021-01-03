<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\AbstractStruct;

final class FakeStruct extends AbstractStruct
{
    public static function fromKVs(...$kvs): AbstractStruct
    {
        $result = new self();
        for ($i = 0, $l = count($kvs); $i < $l; $i += 2) {
            $result[$kvs[$i]] = $kvs[$i+1];
        }
        return $result;
    }

    public function getAllowedKeys(): array
    {
        return ['a', 'b'];
    }
}
