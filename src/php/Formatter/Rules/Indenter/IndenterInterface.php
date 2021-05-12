<?php

declare(strict_types=1);

namespace Phel\Formatter\Rules\Indenter;

use Phel\Formatter\Formatter\ParseTreeZipper;

interface IndenterInterface
{
    public function getMargin(ParseTreeZipper $loc, int $indentWidth): ?int;
}
