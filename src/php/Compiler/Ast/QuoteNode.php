<?php

declare(strict_types=1);

namespace Phel\Compiler\Ast;

use Phel\Compiler\Environment\NodeEnvironmentInterface;
use Phel\Lang\AbstractType;
use Phel\Lang\SourceLocation;

final class QuoteNode extends AbstractNode
{
    /** @var mixed */
    private $value;

    /**
     * @param AbstractType|string|float|int|bool|null $value
     */
    public function __construct(NodeEnvironmentInterface $env, $value, ?SourceLocation $sourceLocation = null)
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
