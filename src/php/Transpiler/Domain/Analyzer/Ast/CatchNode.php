<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

final class CatchNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly AbstractNode $type,
        private readonly Symbol $name,
        private readonly AbstractNode $body,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getType(): AbstractNode
    {
        return $this->type;
    }

    public function getName(): Symbol
    {
        return $this->name;
    }

    public function getBody(): AbstractNode
    {
        return $this->body;
    }
}
