<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Generator;
use Phel\Compiler\ReadModel\ReaderResult;

interface ReaderInterface
{
    public function readNext(Generator $tokenStream): ?ReaderResult;
}
