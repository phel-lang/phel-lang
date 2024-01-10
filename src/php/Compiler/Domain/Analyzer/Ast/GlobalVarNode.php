<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class GlobalVarNode extends AbstractNode
{
    public function __construct(
        NodeEnvironmentInterface $env,
        private readonly string $namespace,
        private readonly Symbol $name,
        private PersistentMapInterface $meta,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getName(): Symbol
    {
        return $this->name;
    }

    public function getMeta(): PersistentMapInterface
    {
        return $this->meta;
    }

    public function isMacro(): bool
    {
        return $this->meta[Keyword::create('macro')] === true;
    }

    public function useReference(): bool
    {
        return $this->getEnv()->useGlobalReference();
    }
}
