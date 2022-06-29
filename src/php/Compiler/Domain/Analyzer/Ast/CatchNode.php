<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class CatchNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private AbstractNode $type,
        private Symbol $name,
        private AbstractNode $body,
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
