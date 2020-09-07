<?php

declare(strict_types=1);

namespace Phel\Lang;

interface IMeta
{
    public function getMeta(): Table;

    public function setMeta(Table $meta): void;
}
