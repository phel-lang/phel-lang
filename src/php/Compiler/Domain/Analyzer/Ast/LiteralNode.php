<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

final class LiteralNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly mixed $value,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
