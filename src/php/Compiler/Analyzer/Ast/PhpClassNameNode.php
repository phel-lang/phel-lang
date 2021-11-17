<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use ReflectionClass;

final class PhpClassNameNode extends AbstractNode
{
    private Symbol $name;

    public function __construct(NodeEnvironmentInterface $env, Symbol $name, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct($env, $sourceLocation);
        $this->name = $name;
    }

    public function getName(): Symbol
    {
        return $this->name;
    }

    /**
     * @psalm-return class-string
     */
    public function getAbsolutePhpName(): string
    {
        /** @psalm-var class-string $classString */
        $classString = '\\' . $this->name->getNamespace() . '\\' . $this->name->getName();
        return $classString;
    }

    public function getReflectionClass(): ReflectionClass
    {
        return new ReflectionClass($this->getAbsolutePhpName());
    }
}
