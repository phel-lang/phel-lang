<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\InNsNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;

use function addslashes;
use function assert;

/**
 * @internal
 */
final class InNsEmitter implements NodeEmitterInterface
{
    use NsStateDefinitionsEmitterTrait;
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof InNsNode);

        $this->emitNamespace($node);

        $this->outputEmitter->emitLine(
            '\\' . GlobalEnvironmentSingleton::class . '::getInstance()->setNs("' . addslashes($node->getNamespace()) . '");',
            $node->getStartSourceLocation(),
        );

        // Update *file* definition to ensure subsequent loads resolve relative paths correctly
        $this->emitFileAndNsDefinitions(
            $node->getNamespace(),
            $node->getStartSourceLocation()?->getFile() ?? '',
        );
    }

    /**
     * A file entered with `in-ns` needs the PHP namespace declaration for the
     * same reason an `ns` file does: `DefStructEmitter` and its def-interface,
     * def-enum and def-exception siblings emit their class inline in FILE and
     * CACHE mode, relying on the file already being namespaced. Without it the
     * class is declared globally while every call site references the qualified
     * name, so it is "not found" on any run that reads the cache.
     *
     * `declarePhpNamespaceOnce` keeps a file that opens with `ns` and later
     * switches with `in-ns` emitting exactly one declaration: PHP requires it to
     * be the very first statement, so the `ns` form wins and this one is a no-op.
     *
     * Safe to emit first here because `LoadEmitter` never inlines a loaded file
     * into its caller - it requires the compiled sibling or loads the source -
     * so a file whose first form is `in-ns` always owns its output file.
     */
    private function emitNamespace(InNsNode $node): void
    {
        $this->outputEmitter->declarePhpNamespaceOnce(
            $node->getNamespace(),
            $node->getStartSourceLocation(),
        );
    }
}
