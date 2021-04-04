<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\TypeInterface;

final class QuoteNode extends AbstractNode
{
    /** @var mixed */
    private $value;

    /**
     * @param TypeInterface|string|float|int|bool|null $value
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
