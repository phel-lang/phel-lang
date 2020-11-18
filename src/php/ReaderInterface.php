<?php

declare(strict_types=1);

namespace Phel;

use Generator;

interface ReaderInterface
{
    public function readNext(Generator $tokenStream): ?ReaderResult;
}
