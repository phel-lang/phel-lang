<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

final class PhpArrayUnsetNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly AbstractNode $arrayExpr,
        private readonly AbstractNode $accessExpr,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getArrayExpr(): AbstractNode
    {
        return $this->arrayExpr;
    }

    public function getAccessExpr(): AbstractNode
    {
        return $this->accessExpr;
    }
}
