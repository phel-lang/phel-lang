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
     * @param AbstractType|string|float|int|bool|null $value
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
