<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LoadNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;

use function addslashes;
use function assert;

final class LoadEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof LoadNode);

        $this->emitPathResolution($node);
        $this->emitFileExistsCheck();
        $this->emitFileEvaluation($node);
    }

    /**
     * Emit the code that produces `$__phelLoadPath` (an absolute path
     * to the file to evaluate) and `$__phelLoadRel` (the user-facing
     * path used in error messages).
     */
    private function emitPathResolution(LoadNode $node): void
    {
        $resolution = $node->getResolution();

        $this->outputEmitter->emitStr('$__phelLoadRel = ');
        $this->outputEmitter->emitLiteral($resolution->path);
        $this->outputEmitter->emitLine(';');

        if ($resolution->isClasspathAbsolute()) {
            $this->emitClasspathAbsoluteSearch();
        } else {
            $this->emitFilesystemPath($resolution->path);
        }
    }

    private function emitFilesystemPath(string $absolutePath): void
    {
        $this->outputEmitter->emitStr('$__phelLoadPath = ');
        $this->outputEmitter->emitLiteral($absolutePath);
        $this->outputEmitter->emitLine(';');
    }

    /**
     * Emit a runtime search across `phel\repl/src-dirs`. First match
     * wins, matching Clojure's classpath-order resolution.
     */
    private function emitClasspathAbsoluteSearch(): void
    {
        $this->outputEmitter->emitLine('$__phelSrcDirs = \\' . Phel::class . '::getDefinition(');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes($this->outputEmitter->mungeEncodeNs('phel\\repl')));
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitLine('"src-dirs"');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine(') ?? [];');

        $this->outputEmitter->emitLine('$__phelLoadPath = null;');
        $this->outputEmitter->emitLine('foreach ($__phelSrcDirs as $__phelSrcDir) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('$__phelCandidate = $__phelSrcDir . \'/\' . $__phelLoadRel;');
        $this->outputEmitter->emitLine('if (file_exists($__phelCandidate)) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('$__phelLoadPath = $__phelCandidate;');
        $this->outputEmitter->emitLine('break;');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
    }

    private function emitFileExistsCheck(): void
    {
        $this->outputEmitter->emitLine('if ($__phelLoadPath === null || !file_exists($__phelLoadPath)) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine(
            "throw new \\RuntimeException(sprintf('Cannot locate %s for (load ...)', \$__phelLoadRel));",
        );
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
    }

    /**
     * Evaluate the resolved file. Restore the previous namespace on exit,
     * and require the loaded file to join the caller namespace via (in-ns ...).
     */
    private function emitFileEvaluation(LoadNode $node): void
    {
        $this->outputEmitter->emitLine('$__phelPrevNs = \\' . GlobalEnvironmentSingleton::class . '::getInstance()->getNs();');
        $this->outputEmitter->emitLine('$__phelLoadedNs = $__phelPrevNs;');
        $this->outputEmitter->emitLine('$__phelBuildFacade = new \\Phel\\Build\\BuildFacade();');
        $this->outputEmitter->emitLine('\\' . BuildFacade::class . '::enableBuildMode();');
        $this->outputEmitter->emitLine('try {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('$__phelBuildFacade->evalFile($__phelLoadPath);');
        $this->outputEmitter->emitLine('$__phelLoadedNs = \\' . GlobalEnvironmentSingleton::class . '::getInstance()->getNs();');
        $this->outputEmitter->emitStr('if ($__phelLoadedNs !== ');
        $this->outputEmitter->emitLiteral($node->getCallerNamespace());
        $this->outputEmitter->emitLine(') {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('throw new \\RuntimeException(sprintf(');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine("'File %s must use (in-ns %s) to join the caller namespace, but found (in-ns %s) or (ns ...)',");
        $this->outputEmitter->emitLine('$__phelLoadPath,');
        $this->outputEmitter->emitLiteral($node->getCallerNamespace());
        $this->outputEmitter->emitLine(',');
        $this->outputEmitter->emitLine('$__phelLoadedNs');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('));');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('} finally {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('\\' . BuildFacade::class . '::disableBuildMode();');
        $this->outputEmitter->emitLine('\\' . GlobalEnvironmentSingleton::class . '::getInstance()->setNs($__phelPrevNs);');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
    }
}
