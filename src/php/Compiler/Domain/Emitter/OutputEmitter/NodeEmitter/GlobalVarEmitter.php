<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\PhpStringEscape;
use Phel\Lang\Keyword;

use function assert;

/**
 * @internal
 */
final class GlobalVarEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof GlobalVarNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr($this->readerFor($node) . '("');

        $this->outputEmitter->emitStr(PhpStringEscape::doubleQuoted($this->outputEmitter->mungeEncodeRegistryKey($node->getNamespace())));
        $this->outputEmitter->emitStr('", "');
        $this->outputEmitter->emitStr(PhpStringEscape::doubleQuoted($node->getName()->getName()));
        $this->outputEmitter->emitStr('")');
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    /**
     * Which read a global compiles to.
     *
     * `\Phel::getDefinition()` checks the dynamic scope before falling through
     * to the registry, and only a `^:dynamic` var can ever have a binding to
     * find there: `binding` refuses anything else. A var that cannot be bound
     * therefore pays a gate that can never fire, on every read, which #3179
     * measured at 10.5% of runtime in a profile. Those reads go straight to
     * the registry root instead.
     *
     * `^:redef` keeps the full path too. It means "stay interceptable", and
     * while `with-redefs` swaps the root a root read would observe anyway,
     * honouring the tag here keeps one answer to "how do I opt this var out of
     * the optimiser" rather than two (#3184).
     *
     * The escape matters for one case: a var compiled without `^:dynamic` and
     * redefined *with* it later in the same process, as a REPL or a reload can
     * do. Readers compiled before that point read the root and will not see
     * its bindings; tagging the var is what says otherwise.
     */
    private function readerFor(GlobalVarNode $node): string
    {
        if ($node->useReference()) {
            return '\\Phel::getDefinitionReference';
        }

        return $this->isBindable($node)
            ? '\\Phel::getDefinition'
            : '\\Phel\\Lang\\Registry::readRoot';
    }

    /**
     * Reads both key forms, as {@see \Phel\Compiler\Domain\Emitter\OutputEmitter\GlobalCallTarget}
     * does: the analyzer writes some metadata under a string key and some under
     * a keyword, and a var tagged either way must keep the full read path.
     */
    private function isBindable(GlobalVarNode $node): bool
    {
        $meta = $node->getMeta();
        return array_any(['dynamic', 'redef'], static fn(string $tag): bool => (bool) $meta->find($tag) || (bool) $meta->find(Keyword::create($tag)));
    }
}
