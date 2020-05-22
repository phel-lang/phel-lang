<?php

namespace Phel\Lang;

use Phel\Stream\SourceLocation;

interface ISourceLocation {

    public function setStartLocation(?SourceLocation $startLocation): void;

    public function setEndLocation(?SourceLocation $endLocation): void;
}