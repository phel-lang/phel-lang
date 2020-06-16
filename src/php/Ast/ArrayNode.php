<?php

declare(strict_types=1);

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

final class ArrayNode extends Node
{
    private array $values;

    public function __construct(NodeEnvironment $env, array $values, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->values = $values;
    }

    public function getValues(): array
    {
        return $this->values;
    }
}
