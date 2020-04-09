<?php

namespace Phel\Lang;

abstract class Phel {

    use SourceLocationTrait;

    public abstract function isTruthy(): bool;

    public abstract function hash();

    public abstract function equals($other): bool;
}