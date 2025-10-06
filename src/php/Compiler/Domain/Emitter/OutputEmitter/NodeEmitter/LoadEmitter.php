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
use function str_ends_with;

final class LoadEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof LoadNode);

        $filePath = $node->getFilePath();

        // Add .phel extension if not present
        if (!str_ends_with($filePath, '.phel')) {
            $filePath .= '.phel';
        }

        // Resolve the file path relative to the current file
        $this->outputEmitter->emitLine('$__phelLoadPath = (function($__phelPath, $__phelCurrentFile) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('if ($__phelPath[0] === \'/\') {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('return $__phelPath;');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
        $this->outputEmitter->emitLine('$__phelDir = dirname($__phelCurrentFile);');
        $this->outputEmitter->emitLine('return $__phelDir . \'/\' . $__phelPath;');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitStr('})(');
        $this->outputEmitter->emitLiteral($filePath);
        $this->outputEmitter->emitStr(', ' . Phel::class . '::getDefinition(');
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes($this->outputEmitter->mungeEncodeNs('phel\\core')));
        $this->outputEmitter->emitStr('", "');
        $this->outputEmitter->emitStr(addslashes('*file*'));
        $this->outputEmitter->emitLine('"));');

        // Check if file exists
        $this->outputEmitter->emitLine('if (!file_exists($__phelLoadPath)) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('throw new \\RuntimeException(sprintf(\'File not found: %s\', $__phelLoadPath));');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');

        // Store the current namespace
        $this->outputEmitter->emitLine('$__phelPrevNs = ' . GlobalEnvironmentSingleton::class . '::getInstance()->getNs();');

        // Evaluate the file
        $this->outputEmitter->emitLine('$__phelBuildFacade = new \\Phel\\Build\\BuildFacade();');
        $this->outputEmitter->emitLine(BuildFacade::class . '::enableBuildMode();');
        $this->outputEmitter->emitLine('try {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('$__phelBuildFacade->evalFile($__phelLoadPath);');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('} finally {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine(BuildFacade::class . '::disableBuildMode();');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');

        // Validate that the loaded file switched to the correct namespace
        $this->outputEmitter->emitLine('$__phelLoadedNs = ' . GlobalEnvironmentSingleton::class . '::getInstance()->getNs();');
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
    }
}
