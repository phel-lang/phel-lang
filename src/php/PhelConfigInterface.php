<?php

declare(strict_types=1);

namespace Phel;

interface PhelConfigInterface
{
    /**
     * @return mixed|null
     */
    public function get(string $key);
}
