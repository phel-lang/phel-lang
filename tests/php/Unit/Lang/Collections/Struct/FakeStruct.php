<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Struct;

use Phel\Lang\Collections\Struct\AbstractPersistentStruct;

final class FakeStruct extends AbstractPersistentStruct
{
    protected const ALLOWED_KEYS = ['a', 'b'];

    protected $a;
    protected $b;

    public function __construct($a, $b, $__meta = null)
    {
        parent::__construct();
        $this->a = $a;
        $this->b = $b;
        $this->__meta = $__meta;
    }

    public static function fromKVs(...$kvs): AbstractPersistentStruct
    {
        $result = new self(null, null, null);
        for ($i = 0, $l = count($kvs); $i < $l; $i += 2) {
            $result = $result->put($kvs[$i], $kvs[$i + 1]);
        }
        return $result;
    }
}
