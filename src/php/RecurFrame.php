<?php

namespace Phel;

use Phel\Lang\Symbol;

class RecurFrame
{

    /**
     * @var bool
     */
    private $isActive = false;

    /**
     * @var Symbol[]
     */
    private $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}
