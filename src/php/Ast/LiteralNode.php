<?php

declare(strict_types=1);

namespace Phel\Ast;

use Phel\Lang\AbstractType;
use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

final class LiteralNode extends Node
{
    /** @var AbstractType|scalar|null */
    private $value;

    /**
     * @param AbstractType|scalar|null $value
     */
    public function __construct(NodeEnvironment $env, $value, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->value = $value;
    }

    /**
     * @return AbstractType|scalar|null
     */
    public function getValue()
    {
        return $this->value;
    }
}
