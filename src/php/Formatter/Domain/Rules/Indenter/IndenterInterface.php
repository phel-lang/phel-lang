<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Indenter;

use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;

interface IndenterInterface
{
    public function getMargin(ParseTreeZipper $loc, int $indentWidth): ?int;
}
