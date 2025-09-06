<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class PhpArrayPushNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly AbstractNode $arrayExpr,
        /** @var list<AbstractNode> */
        private readonly array $accessExprs,
        private readonly AbstractNode $valueExpr,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getArrayExpr(): AbstractNode
    {
        return $this->arrayExpr;
    }

    /**
     * @return list<AbstractNode>
     */
    public function getAccessExprs(): array
    {
        return $this->accessExprs;
    }

    public function getValueExpr(): AbstractNode
    {
        return $this->valueExpr;
    }
}
