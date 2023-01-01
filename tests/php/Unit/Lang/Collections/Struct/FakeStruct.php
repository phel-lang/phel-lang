<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Struct;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Struct\AbstractPersistentStruct;

use function count;

final class FakeStruct extends AbstractPersistentStruct
{
    protected const ALLOWED_KEYS = ['a', 'b'];

    public function __construct(
        protected $a,
        protected $b,
    ) {
        parent::__construct();
    }

    public static function fromKVs(mixed ...$kvs): PersistentMapInterface
    {
        $result = new self(null, null);
        for ($i = 0, $l = count($kvs); $i < $l; $i += 2) {
            $result = $result->put($kvs[$i], $kvs[$i + 1]);
        }
        return $result;
    }
}
