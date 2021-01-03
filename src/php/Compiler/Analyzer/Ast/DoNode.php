<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class DoNode extends AbstractNode
{
    /** @var AbstractNode[] */
    private array $stmts;

    private AbstractNode $ret;

    /**
     * @param AbstractNode[] $stmts
     */
    public function __construct(NodeEnvironmentInterface $env, array $stmts, AbstractNode $ret, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->stmts = $stmts;
        $this->ret = $ret;
    }

    /**
     * @return AbstractNode[]
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
