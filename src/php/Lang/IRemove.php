<?php

namespace Phel\Lang;

interface IRemove {
    public function remove($offest, $length = null): IRemove;
}