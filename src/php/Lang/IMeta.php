<?php

namespace Phel\Lang;

interface IMeta
{
    public function getMeta(): Table;

    public function setMeta(Table $meta);
}
