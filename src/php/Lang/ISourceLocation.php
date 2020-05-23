<?php

namespace Phel\Lang;

interface ISourceLocation {

    public function setStartLocation(?SourceLocation $startLocation): void;

    public function setEndLocation(?SourceLocation $endLocation): void;
}