<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Lang\Collections\Struct\AbstractPersistentStruct;

final class StubStruct extends AbstractPersistentStruct
{
    protected const ALLOWED_KEYS = ['a', 'b'];
    protected $a;
    protected $b;
    private $allowedKeys;

    public function __construct(array $allowedKeys)
    {
        parent::__construct();
        $this->allowedKeys = $allowedKeys;
    }

    public function getAllowedKeys(): array
    {
        return $this->allowedKeys;
    }
}
