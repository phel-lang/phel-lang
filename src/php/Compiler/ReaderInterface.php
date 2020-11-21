<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Generator;

interface ReaderInterface
{
    public function readNext(Generator $tokenStream): ?ReaderResult;
}
