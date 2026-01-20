<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\NsNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\RuntimeClassReference;

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
        // Both FILE and CACHE modes need PHP namespace declaration for struct classes
        if ($this->outputEmitter->getOptions()->isFileEmitMode()
            || $this->outputEmitter->getOptions()->isCacheEmitMode()
        ) {
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
        } elseif ($this->outputEmitter->getOptions()->isCacheEmitMode()) {
            // In cache mode, don't emit any dependency loading code.
            // Dependencies are loaded in order by the test framework.
        } else {
            $this->outputEmitter->emitLine('$__phelBuildFacade = new ' . RuntimeClassReference::BUILD_FACADE . '();');
            $this->outputEmitter->emitLine('$__phelSrcDirs = ' . RuntimeClassReference::PHEL . '::getDefinition(');
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitStr('"');
            $this->outputEmitter->emitStr(addslashes($this->outputEmitter->mungeEncodeNs('phel\\repl')));
            $this->outputEmitter->emitLine('",');
            $this->outputEmitter->emitStr('"src-dirs"');
            $this->outputEmitter->emitLine(') ?? [];');
            $this->outputEmitter->decreaseIndentLevel();

            foreach ($node->getRequireNs() as $ns) {
                $this->outputEmitter->emitLine(
                    '$__phelNsInfos = $__phelBuildFacade->getDependenciesForNamespace($__phelSrcDirs, ['
                    . "'" . addslashes($ns->getName()) . "'"
                    . ']);',
                );
                $this->outputEmitter->emitLine('foreach ($__phelNsInfos as $__phelNsInfo) {');
                $this->outputEmitter->increaseIndentLevel();
                $this->outputEmitter->emitLine('if (!in_array($__phelNsInfo->getNamespace(), ' . RuntimeClassReference::PHEL . '::getNamespaces(), true)) {');
                $this->outputEmitter->increaseIndentLevel();
                $this->outputEmitter->emitLine(RuntimeClassReference::BUILD_FACADE . '::enableBuildMode();');
                $this->outputEmitter->emitLine('$__phelBuildFacade->evalFile($__phelNsInfo->getFile());');
                $this->outputEmitter->emitLine(RuntimeClassReference::BUILD_FACADE . '::disableBuildMode();');
                $this->outputEmitter->emitLine(RuntimeClassReference::GLOBAL_ENVIRONMENT_SINGLETON . '::getInstance()->setNs("' . addslashes($node->getNamespace()) . '");');
                $this->outputEmitter->decreaseIndentLevel();
                $this->outputEmitter->emitLine('}');
                $this->outputEmitter->decreaseIndentLevel();
                $this->outputEmitter->emitLine('}');
            }
        }
    }

    private function emitCurrentNamespace(NsNode $node): void
    {
        if (!$this->outputEmitter->getOptions()->isFileEmitMode()) {
            $this->outputEmitter->emitLine(
                RuntimeClassReference::GLOBAL_ENVIRONMENT_SINGLETON . '::getInstance()->setNs("' . addslashes($node->getNamespace()) . '");',
                $node->getStartSourceLocation(),
            );
        }

        $this->outputEmitter->emitLine(RuntimeClassReference::PHEL . '::addDefinition(');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes($this->outputEmitter->mungeEncodeNs('phel\\core')));
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes('*file*'));
        $this->outputEmitter->emitLine('",');

        $file = $node->getStartSourceLocation()?->getFile() ?? '';
        $this->outputEmitter->emitLiteral($file);
        $this->outputEmitter->emitLine();
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine(');');

        $this->outputEmitter->emitLine(RuntimeClassReference::PHEL . '::addDefinition(');
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
