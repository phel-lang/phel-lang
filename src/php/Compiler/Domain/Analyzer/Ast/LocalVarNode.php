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
     * and the `ParamTypeInferrer` pass). Returns `null` when the binding
     * has no known type - the call site falls back to runtime dispatch.
     *
     * `AnalyzeSymbol` builds the `LocalVarNode`'s own `Symbol` from the
     * call-site syntax. For `fn` params the symbol matches the binding
     * name verbatim; for `let` / `loop` bindings the symbol carries the
     * **shadowed** name (e.g. `a_3` for `(let [a 0] ...)`). We therefore
     * try a direct name match first, then fall back to the env's reverse
     * shadow lookup so a shadowed reference still resolves to the typed
     * binding meta.
     */
    public function getInferredType(): ?string
    {
        $name = $this->name->getName();
        foreach ($this->getEnv()->getLocals() as $local) {
            if ($local->getName() === $name) {
                return $this->tagOf($local->getMeta());
            }
        }

        $shadowedSource = $this->getEnv()->findLocalByShadowedName($name);
        if ($shadowedSource instanceof Symbol) {
            return $this->tagOf($shadowedSource->getMeta());
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
