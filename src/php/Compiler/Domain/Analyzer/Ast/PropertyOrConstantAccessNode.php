<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class PropertyOrConstantAccessNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly Symbol $name,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getName(): Symbol
    {
        return $this->name;
    }
}
