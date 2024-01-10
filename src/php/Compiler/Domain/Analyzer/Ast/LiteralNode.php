<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\TypeInterface;

final class LiteralNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly TypeInterface|array|string|float|int|bool|null $value,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getValue(): TypeInterface|array|string|float|int|bool|null
    {
        return $this->value;
    }
}
