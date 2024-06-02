<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

/**
 * @template T
 */
interface Fnable
{
    /**
     * @return T
     */
    public function getFn();

    /**
     * @return list<AbstractNode>
     */
    public function getArguments(): array;
}
