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
    use NsStateDefinitionsEmitterTrait;
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
        // Both FILE and CACHE modes need the PHP namespace declaration for
        // struct classes, which are emitted inline into the enclosing namespace.
        $this->outputEmitter->declarePhpNamespaceOnce(
            $node->getNamespace(),
            $node->getStartSourceLocation(),
        );
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
                $depth = count(explode('.', $node->getNamespace())) - 1;
                $filename = str_replace('\\', '/', $this->outputEmitter->mungeEncodePhpNs($ns->getName()));
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
            $this->outputEmitter->emitLine('$__phelBuildFacade = new \\Phel\\Build\\BuildFacade();');
            $this->outputEmitter->emitLine('$__phelSrcDirs = [];');
            $this->outputEmitter->emitLine('if (\\Phel::getDefinition(');
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitStr('"');
            $this->outputEmitter->emitStr(addslashes($this->outputEmitter->mungeEncodeRegistryKey('phel.core')));
            $this->outputEmitter->emitLine('",');
            $this->outputEmitter->emitStr('"');
            $this->outputEmitter->emitStr(addslashes('*repl-mode*'));
            $this->outputEmitter->emitLine('")) {');
            $this->outputEmitter->emitLine('$__phelSrcDirs = (new \\Phel\\Command\\CommandFacade())->getAllPhelDirectories();');
            $this->outputEmitter->emitLine('$__phelCwd = getcwd();');
            $this->outputEmitter->emitLine('if ($__phelCwd !== false) { $__phelSrcDirs[] = $__phelCwd; }');
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitLine('}');

            foreach ($node->getRequireNs() as $ns) {
                $this->outputEmitter->emitLine(
                    '$__phelNsInfos = $__phelBuildFacade->getDependenciesForNamespace($__phelSrcDirs, ['
                    . "'" . addslashes($ns->getName()) . "'"
                    . ']);',
                );
                $this->outputEmitter->emitLine('foreach ($__phelNsInfos as $__phelNsInfo) {');
                $this->outputEmitter->increaseIndentLevel();
                // `getNamespace()` is the canonical Phel form (`my-app.lib`);
                // registry keys are munged (`my_app.lib`). Comparing them
                // directly made every kebab-case namespace look unloaded, so
                // each require re-evaluated its file and re-ran its top level.
                $this->outputEmitter->emitLine('if (!\\Phel::isNamespaceLoaded($__phelNsInfo->getNamespace())) {');
                $this->outputEmitter->increaseIndentLevel();
                $this->outputEmitter->emitLine('\\Phel\\Build\\BuildFacade::enableBuildMode();');
                $this->outputEmitter->emitLine('$__phelBuildFacade->evalFile($__phelNsInfo->getFile());');
                $this->outputEmitter->emitLine('\\Phel\\Build\\BuildFacade::disableBuildMode();');
                $this->outputEmitter->emitLine('\\Phel\\Compiler\\Infrastructure\\GlobalEnvironmentSingleton::getInstance()->setNs("' . addslashes($node->getNamespace()) . '");');
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
                '\Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton::getInstance()->setNs("' . addslashes($node->getNamespace()) . '");',
                $node->getStartSourceLocation(),
            );
        }

        $this->emitFileAndNsDefinitions(
            $node->getNamespace(),
            $node->getStartSourceLocation()?->getFile() ?? '',
        );
    }
}
