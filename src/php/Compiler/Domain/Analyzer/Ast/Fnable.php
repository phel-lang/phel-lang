<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;

/**
 * @template T
 */
interface Fnable
{
    public function getStartSourceLocation(): ?SourceLocation;

    /**
     * @return T
     */
    public function getFn();

    /**
     * @return list<AbstractNode>
     */
    public function getArgs(): array;
}
