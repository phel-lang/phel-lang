<?php

declare(strict_types=1);

namespace Phel\Ast;

use Phel\Lang\AbstractType;
use Phel\Lang\SourceLocation;
use Phel\NodeEnvironment;

final class QuoteNode extends Node
{
    /** @var mixed */
    private $value;

    /**
     * @param NodeEnvironment $env The node environment
     * @param AbstractType|scalar|null $value The value
     * @param ?SourceLocation $sourceLocation The source location
     */
    public function __construct(NodeEnvironment $env, $value, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
