<?php

declare(strict_types=1);

namespace Phel\Ast;

use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

final class TableNode extends Node
{
    private array $keyValues;

    public function __construct(NodeEnvironment $env, array $keyValues, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->keyValues = $keyValues;
    }

    public function getKeyValues(): array
    {
        return $this->keyValues;
    }
}
