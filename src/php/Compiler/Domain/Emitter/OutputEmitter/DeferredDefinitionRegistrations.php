<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;

use function array_keys;
use function implode;

/**
 * A cache-mode file registers every top-level `def` in the global environment
 * with one call at its end, instead of a guarded block per definition: on
 * `phel.core` that was 736 blocks, each building a `Symbol` and asking the
 * environment twice. Only a `def` that is itself a top-level form is
 * deferred. One nested in a fn or a `when` registers where it runs, as it
 * always did, because it may never run.
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

    public function enterTopLevelForm(AbstractNode $node): void
    {
        $this->topLevelForm = $node;
    }

    /**
     * Records the definition for the end-of-file call when it is the
     * top-level form being emitted, and says whether it was.
     *
     * @param string $namespace the registry key, already munged
     */
    public function defer(DefNode $node, string $namespace): bool
    {
        if ($node !== $this->topLevelForm) {
            return false;
        }

        $this->names[$namespace][$node->getName()->getName()] = true;

        return true;
    }

    /**
     * The PHP statement registering every deferred definition, or '' when
     * there is none.
     */
    public function toPhp(): string
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

        return '\\' . GlobalEnvironmentSingleton::class . '::getInstance()->addCompiledDefinitions([' . implode(', ', $namespaces) . "]);\n";
    }
}
