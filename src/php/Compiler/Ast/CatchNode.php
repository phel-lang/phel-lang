<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class CatchNode extends AbstractNode
{
    private Symbol $type;
    private Symbol $name;
    private AbstractNode $body;

    public function __construct(
        NodeEnvironmentInterface $env,
        Symbol $type,
        Symbol $name,
        AbstractNode $body,
        ?SourceLocation $sourceLocation = null
    ) {
        parent::__construct($env, $sourceLocation);
        $this->type = $type;
        $this->name = $name;
        $this->body = $body;
    }

    public function getType(): Symbol
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
