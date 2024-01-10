<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\NsNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function addslashes;
use function assert;
use function count;

final class NsEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof NsNode);

        $this->emitNamespace($node);
        $this->emitRequireFiles($node);
        $this->emitRequiredNamespaces($node);
        $this->emitCurrentNamespace($node);
    }

    private function emitNamespace(NsNode $node): void
    {
        if ($this->outputEmitter->getOptions()->isFileEmitMode()) {
            $this->outputEmitter->emitStr('namespace ', $node->getStartSourceLocation());
            $this->outputEmitter->emitStr($this->outputEmitter->mungeEncodeNs($node->getNamespace()), $node->getStartSourceLocation());
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }
    }

    private function emitRequireFiles(NsNode $node): void
    {
        if ($this->outputEmitter->getOptions()->isFileEmitMode()) {
            foreach ($node->getRequireFiles() as $path) {
                $this->outputEmitter->emitStr('require_once ', $node->getStartSourceLocation());
                $this->outputEmitter->emitLiteral($path);
                $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
            }
        }
    }

    private function emitRequiredNamespaces(NsNode $node): void
    {
        if ($this->outputEmitter->getOptions()->isFileEmitMode()) {
            foreach ($node->getRequireNs() as $ns) {
                $depth = count(explode('\\', $node->getNamespace())) - 1;
                $filename = str_replace('\\', '/', $this->outputEmitter->mungeEncodeNs($ns->getName()));
                $relativePath = str_repeat('/..', $depth) . '/' . $filename . '.php';
                $absolutePath = "__DIR__ . '" . $relativePath . "'";

                $this->outputEmitter->emitLine(
                    'require_once ' . $absolutePath . ';',
                    $ns->getStartLocation(),
                );
            }
        }
    }

    private function emitCurrentNamespace(NsNode $node): void
    {
        if (!$this->outputEmitter->getOptions()->isFileEmitMode()) {
            $this->outputEmitter->emitLine(
                '\Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton::getInstance()->setNs("' . addslashes($node->getNamespace()) . '");',
                $node->getStartSourceLocation(),
            );
        }

        $this->outputEmitter->emitLine('\\Phel\\Lang\\Registry::getInstance()->addDefinition(');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes($this->outputEmitter->mungeEncodeNs('phel\\core')));
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes('*ns*'));
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitLiteral($this->outputEmitter->mungeEncodeNs($node->getNamespace()));
        $this->outputEmitter->emitLine();
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine(');');
    }
}
