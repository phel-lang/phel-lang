<?php

namespace Phel;

use Phel\Lang\Symbol;

class RecurFrame {

    private $isActive = false;

    /**
     * @var Symbol[]
     */
    private $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function setIsActive($isActive) {
        $this->isActive = $isActive;
    }

    public function isActive() {
        return $this->isActive;
    }

    public function getParams() {
        return $this->params;
    }
}