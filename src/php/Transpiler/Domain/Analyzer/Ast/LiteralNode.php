<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Ast;

use Phel\Lang\SourceLocation;
use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

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
