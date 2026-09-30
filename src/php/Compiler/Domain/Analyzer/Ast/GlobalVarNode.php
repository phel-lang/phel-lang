<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

/**
 * @internal
 */
final class GlobalVarNode extends AbstractNode
{
    /**
     * @param PersistentMapInterface<mixed, mixed> $meta
     */
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

    /**
     * @return PersistentMapInterface<mixed, mixed>
     */
    public function getMeta(): PersistentMapInterface
    {
        return $this->meta;
    }

    /**
     * A `^:dynamic` or `^:redef` global may be a different fn by the time a
     * call runs (`binding`, `with-redefs`), so its definition proves nothing
     * about the fn the call reaches.
     */
    public function isRebindable(): bool
    {
        return (bool) $this->meta[Keyword::create('dynamic')] || (bool) $this->meta[Keyword::create('redef')];
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
