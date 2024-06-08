<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Application\Munge;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use ReflectionClass;

final class PhpClassNameNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly Symbol $name,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
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
        if ($this->name->getNamespace() !== null && $this->name->getNamespace() !== '') {
            $munge = new Munge();
            $mungeNs = $munge->encodeNs($this->name->getNamespace());
            /** @psalm-var class-string $classString */
            $classString = '\\' . $mungeNs . '\\' . $this->name->getName();
            return $classString;
        }

        /** @psalm-var class-string $classString */
        $classString = $this->name->getName();
        return $classString;
    }

    public function getReflectionClass(): ReflectionClass
    {
        return new ReflectionClass($this->getAbsolutePhpName());
    }
}
