<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

final class DoNode extends AbstractNode
{
    /**
     * @param list<AbstractNode> $stmts
     */
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly array $stmts,
        private readonly AbstractNode $ret,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    /**
     * @return list<AbstractNode>
     */
    public function getStmts(): array
    {
        return $this->stmts;
    }

    public function getRet(): AbstractNode
    {
        return $this->ret;
    }
}
