<?php

namespace Phel\Lang;

class Truthy {

    public static function isTruthy($value) {
        if ($value instanceof Phel) {
            return $value->isTruthy();
        } else {
            return $value != null && $value !== false;
        }
    }
}