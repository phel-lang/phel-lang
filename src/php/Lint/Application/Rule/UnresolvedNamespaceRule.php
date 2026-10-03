<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\KnownNamespacesInterface;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\FrameworkNamespaces;
use Phel\Shared\LintRuleCodes;
use Phel\Shared\Munge;

use function array_flip;
use function count;
use function sprintf;

/**
 * Flags a `(:require ...)` of a `phel.*` or `clojure.*` namespace that Phel
 * does not ship, such as `phel.strng` or the pre-0.33 `phel.str`. A `clojure.*`
 * require resolves through its `phel.*` target (`clojure.set` is `phel.core`).
 * A user namespace is left to the runtime, which already names the missing one.
 *
 * @internal
 */
final readonly class UnresolvedNamespaceRule implements LintRuleInterface
{
    public function __construct(
        private KnownNamespacesInterface $knownNamespaces,
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    public function code(): string
    {
        return LintRuleCodes::UNRESOLVED_NAMESPACE;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $nsForm = NamespaceForm::find($analysis->forms);
        if (!$nsForm instanceof PersistentListInterface) {
            return [];
        }

        $known = null;
        $result = [];
        foreach (NsClauseIterator::clauses($nsForm, 'require') as $clause) {
            foreach ($this->requiredNamespaces($clause) as $required) {
                $name = Munge::canonicalNs($required->getName());
                $target = FrameworkNamespaces::clojureTarget($name);
                if ($target === null && !FrameworkNamespaces::isPhel($name)) {
                    continue;
                }

                $known ??= array_flip($this->knownNamespaces->all());
                if (isset($known[$name]) || ($target !== null && isset($known[$target]))) {
                    continue;
                }

                $result[] = DiagnosticBuilder::fromForm(
                    $this->code(),
                    $this->message($name, $target ?? $name),
                    $analysis->uri,
                    $required,
                );
            }
        }

        return $result;
    }

    /**
     * The namespace of each entry: the head of `[foo :as f]`, or a bare
     * symbol of the flat form `(:require foo :as f)`, skipping every option
     * keyword and its value.
     *
     * @param PersistentListInterface<mixed> $clause
     *
     * @return list<Symbol>
     */
    private function requiredNamespaces(PersistentListInterface $clause): array
    {
        $result = [];
        $size = count($clause);
        for ($i = 1; $i < $size; ++$i) {
            $item = $clause->get($i);
            if ($item instanceof Keyword) {
                ++$i;

                continue;
            }

            if ($item instanceof PersistentVectorInterface && count($item) > 0) {
                $item = $item->get(0);
            }

            if ($item instanceof Symbol) {
                $result[] = $item;
            }
        }

        return $result;
    }

    private function message(string $name, string $phelName): string
    {
        $suggestions = $this->compilerFacade->findSimilarNames($phelName, $this->knownNamespaces->all());
        if ($suggestions === []) {
            return sprintf("Cannot find namespace '%s'.", $name);
        }

        return sprintf("Cannot find namespace '%s'. Did you mean '%s'?", $name, $suggestions[0]);
    }
}
