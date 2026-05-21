<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

use function is_string;

final class LocalVarNode extends AbstractNode
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
     * Inferred type for this reference, derived from the binding's `:tag`
     * meta (set by `defn` param tags, `let` `^Type` annotations, and the
     * `ParamTypeInferrer` pass). Returns `null` when the binding has no
     * known type — the call site falls back to runtime dispatch.
     *
     * `AnalyzeSymbol` builds the `LocalVarNode`'s own `Symbol` from the
     * call-site syntax, which has no tag of its own, so the lookup walks
     * the current environment's locals by name to find the typed binding.
     */
    public function getInferredType(): ?string
    {
        $name = $this->name->getName();
        foreach ($this->getEnv()->getLocals() as $local) {
            if ($local->getName() === $name) {
                return $this->tagOf($local->getMeta());
            }
        }

        return null;
    }

    private function tagOf(mixed $meta): ?string
    {
        if (!$meta instanceof PersistentMapInterface) {
            return null;
        }

        $tag = $meta->find(Keyword::create('tag'));
        if ($tag instanceof Symbol) {
            $tag = $tag->getName();
        }

        return is_string($tag) && $tag !== '' ? $tag : null;
    }
}
