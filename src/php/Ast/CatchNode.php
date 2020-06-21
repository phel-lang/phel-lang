<?php

declare(strict_types=1);

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\NodeEnvironment;

final class CatchNode extends Node
{
    private Symbol $type;
    private Symbol $name;
    private Node $body;

    public function __construct(
        NodeEnvironment $env,
        Symbol $type,
        Symbol $name,
        Node $body,
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

    public function getBody(): Node
    {
        return $this->body;
    }
}
