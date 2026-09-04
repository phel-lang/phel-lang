<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\PhpStringEscape;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;

use function assert;

/**
 * @internal
 */
final class DefEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefNode);

        // In cache mode, also register the definition in GlobalEnvironment
        // so the analyzer can resolve symbols when other files have cache misses
        if ($this->outputEmitter->getOptions()->isCacheEmitMode()) {
            $ns = PhpStringEscape::doubleQuoted($this->outputEmitter->mungeEncodeRegistryKey($node->getNamespace()));
            $name = PhpStringEscape::doubleQuoted($node->getName()->getName());
            $this->outputEmitter->emitLine('if (!\\' . GlobalEnvironmentSingleton::class . '::getInstance()->hasDefinition("' . $ns . '", \\' . Symbol::class . '::create("' . $name . '"))) {');
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitLine('\\' . GlobalEnvironmentSingleton::class . '::getInstance()->addDefinition(');
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitStr('"');
            $this->outputEmitter->emitStr($ns);
            $this->outputEmitter->emitLine('",');
            $this->outputEmitter->emitLine('\\' . Symbol::class . '::create("' . $name . '")');
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitLine(');');
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitLine('}');
        }

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $ns = PhpStringEscape::doubleQuoted($this->outputEmitter->mungeEncodeRegistryKey($node->getNamespace()));
        $name = PhpStringEscape::doubleQuoted($node->getName()->getName());

        if ($node->isDefonce()) {
            // `defonce` keeps the existing binding intact across re-eval
            // — useful for REPL workflows where re-loading a file would
            // otherwise reset stateful atoms / connections / caches.
            // `isDefined` (not `hasDefinition`) is the correct guard: it
            // routes to `Registry::isDefined`, which uses `array_key_exists`
            // and so distinguishes a stored `null` from a missing entry.
            // `hasDefinition` would overwrite `(defonce x nil)` on reload.
            $this->outputEmitter->emitLine('if (!\\Phel::isDefined("' . $ns . '", "' . $name . '")) {');
            $this->outputEmitter->increaseIndentLevel();
        }

        $this->outputEmitter->emitLine('\\Phel::addDefinition(');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr($ns);
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr($name);
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitNode($node->getInit());
        if ($node->getMeta()->getKeyValues() !== []) {
            $this->outputEmitter->emitLine(',');
            // Deferred, not because the map is large but because there are so
            // many of them: loading a namespace builds one per definition, and
            // the readers (`phel doc`, `(meta …)`, the LSP, and the analyzer
            // asking about `:macro` or `:inline`) touch a small fraction of
            // them. `Registry::getDefinitionMetaData()` forces the closure on
            // first read and replaces it with the map.
            $this->outputEmitter->emitStr('static fn() => ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($node->getMeta());
        }

        $this->outputEmitter->emitLine();
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine(');');

        if ($node->isDefonce()) {
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitLine('}');
        }
    }
}
