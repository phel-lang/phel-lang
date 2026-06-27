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
     * meta (set by `defn` param tags, `let` / `loop` `^Type` annotations,
     * the `ParamTypeInferrer`, and the `BindingTypeInferrer`). Returns
     * `null` when the binding has no known type — the call site falls back
     * to runtime dispatch.
     *
     * `AnalyzeSymbol` builds this node's `Symbol` from `env->getShadowed(...)`,
     * so for a `let` / `loop` reference it is the binding's **unique shadow**
     * symbol (e.g. `a_3` for `(let [a 0] ...)`), and for an fn param — which
     * is not shadowed — the reference symbol itself. The binding's `:tag` is
     * mirrored onto that shadow, so reading the reference symbol's own meta
     * resolves the tag **exactly**, even when the name is reused in a nested
     * scope. A by-name env lookup would instead resolve a shadowed inner
     * reference to the wrong (outer) binding of the same name. A param's
     * reference symbol carries no meta, so its tag is matched by name against
     * the declared local in scope.
     */
    public function getInferredType(): ?string
    {
        $ownTag = $this->tagOf($this->name->getMeta());
        if ($ownTag !== null) {
            return $ownTag;
        }

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
