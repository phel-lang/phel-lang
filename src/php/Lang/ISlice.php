<?php

namespace Phel\Lang;

interface ISlice {
    public function slice($offset = 0, $length = null): ISlice;
}