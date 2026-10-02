<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;

use function array_keys;
use function implode;

/**
 * A cache-mode file registers every top-level `def` in the global environment
 * with one call at its end, instead of a guarded block per definition: on
 * `phel.core` that was 736 blocks, each building a `Symbol` and asking the
 * environment twice. Only a `def` that is itself a top-level form, with a
 * fn or a constant as its value, is deferred. One nested in a fn or a `when`
 * registers where it runs, as it always did, because it may never run.
 *
 * Any other top-level form can run code that compiles another file, as
 * `(load ...)` does, or throw. So the pending batch is emitted before it, and
 * every definition that ran before it is already known to the analyzer.
 *
 * @internal
 */
final class DeferredDefinitionRegistrations
{
    private ?AbstractNode $topLevelForm = null;

    /** @var array<string, array<string, true>> */
    private array $names = [];

    public function reset(): void
    {
        $this->topLevelForm = null;
        $this->names = [];
    }

    /**
     * Starts a top-level form. Returns the PHP statement registering the
     * pending definitions when the form may run code before they are known,
     * or '' when nothing has to be emitted first.
     */
    public function enterTopLevelForm(AbstractNode $node): string
    {
        $this->topLevelForm = $node;

        return $this->isDeferrable($node) ? '' : $this->flush();
    }

    /**
     * Records the definition for the end-of-file call when it is the
     * top-level form being emitted, and says whether it was.
     *
     * @param string $namespace the registry key, already munged
     */
    public function defer(DefNode $node, string $namespace): bool
    {
        if ($node !== $this->topLevelForm || !$this->isDeferrable($node)) {
            return false;
        }

        $this->names[$namespace][$node->getName()->getName()] = true;

        return true;
    }

    /**
     * The PHP statement registering every pending definition, or '' when
     * there is none. The pending list is emptied.
     */
    public function flush(): string
    {
        if ($this->names === []) {
            return '';
        }

        $namespaces = [];
        foreach ($this->names as $namespace => $names) {
            $quoted = [];
            foreach (array_keys($names) as $name) {
                $quoted[] = '"' . PhpStringEscape::doubleQuoted((string) $name) . '"';
            }

            $namespaces[] = '"' . PhpStringEscape::doubleQuoted($namespace) . '" => [' . implode(', ', $quoted) . ']';
        }

        $this->names = [];

        return '\\' . GlobalEnvironmentSingleton::class . '::getInstance()->addCompiledDefinitions([' . implode(', ', $namespaces) . ']);';
    }

    private function isDeferrable(AbstractNode $node): bool
    {
        if (!$node instanceof DefNode) {
            return false;
        }

        $init = $node->getInit();

        return $init instanceof FnNode
            || $init instanceof MultiFnNode
            || $init instanceof LiteralNode
            || $init instanceof QuoteNode;
    }
}
