<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\Struct;

final class StructTestTable extends Struct
{
    public static function fromKVs(...$kvs): Struct
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
