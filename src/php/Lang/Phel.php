<?php

namespace Phel\Lang;

interface Phel {

    public function isTruthy(): bool;

    public function hash();

    public function equals($other): bool;
}