<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashMap;

class Box
{
    /** @var mixed */
    private $value;

    /**
     * @param mixed $value
     */
    public function __construct($value)
    {
        //echo "create Box\n";
        $this->value = $value;
    }

    public function __destruct()
    {
        //echo "destruct Box\n";
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }
}
