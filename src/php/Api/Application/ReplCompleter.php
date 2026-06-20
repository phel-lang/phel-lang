<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel;
use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Api\Domain\ReplCompleterInterface;
use Phel\Api\Transfer\CompletionResultTransfer;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\Keyword;

use function str_starts_with;
use function trim;

/**
 * Lazy-loading autocompleter for REPL suggestions in both PHP and Phel contexts.
 *
 * The first call to {@see complete()} / {@see completeWithTypes()} pays a one-time
 * setup cost: all Phel functions are loaded via the loader and the flag
 * {@see $phelFunctionsLoaded} is set so subsequent calls reuse the registry.
 * PHP builtin symbols are cached eagerly in the {@see PhpSymbolCatalog} created
 * in the constructor.
 */
final class ReplCompleter implements ReplCompleterInterface
{
    private bool $phelFunctionsLoaded = false;

    private readonly PhpSymbolCatalog $phpSymbols;

    /**
     * @param list<string> $allNamespaces
     * @param list<string> $nativeSymbolNames special-form / native symbol names
     *                                        (def, fn, let, ...) to offer as
     *                                        completions
     */
    public function __construct(
        private readonly PhelFnLoaderInterface $phelFnLoader,
        private readonly array $allNamespaces = [],
        private readonly ?GlobalEnvironmentInterface $globalEnvironment = null,
        ?PhpSymbolCatalog $phpSymbols = null,
        private readonly array $nativeSymbolNames = [],
    ) {
        $this->phpSymbols = $phpSymbols ?? new PhpSymbolCatalog();
    }

    /**
     * Complete input from either PHP or Phel context.
     *
     * @param string $input the input string for which to find completions
     *
     * @return list<string> a list of autocompletion suggestions
     */
    public function complete(string $input): array
    {
        return array_map(
            static fn(CompletionResultTransfer $r): string => $r->candidate,
            $this->completeWithTypes($input),
        );
    }

    /**
     * Complete input with type annotations for nREPL clients.
     *
     * @return list<CompletionResultTransfer>
     */
    public function completeWithTypes(string $input): array
    {
        if (!$this->phelFunctionsLoaded) {
            $this->phelFnLoader->loadAllPhelFunctions($this->allNamespaces);
            $this->phelFunctionsLoaded = true;
        }

        $input = trim($input);
        if ($input === '') {
            return [];
        }

        return str_starts_with($input, 'php/')
            ? $this->completePhpSymbolsWithTypes(substr($input, 4))
            : $this->completePhelWithTypes($input);
    }

    /**
     * Complete native PHP functions and classes with type annotations.
     *
     * @param string $prefix the input string without the `php/` prefix
     *
     * @return list<CompletionResultTransfer>
     */
    private function completePhpSymbolsWithTypes(string $prefix): array
    {
        $matches = [];

        foreach ($this->phpSymbols->functions() as $fn) {
            if (str_starts_with($fn, $prefix)) {
                $matches[] = new CompletionResultTransfer('php/' . $fn, 'php-function');
            }
        }

        foreach ($this->phpSymbols->classes() as $class) {
            if (str_starts_with($class, $prefix)) {
                $matches[] = new CompletionResultTransfer('php/' . $class, 'class');
            }
        }

        usort($matches, static fn(CompletionResultTransfer $a, CompletionResultTransfer $b): int => $a->candidate <=> $b->candidate);

        return $matches;
    }

    /**
     * Complete available Phel definitions with type annotations.
     *
     * @return list<CompletionResultTransfer>
     */
    private function completePhelWithTypes(string $input): array
    {
        /** @var array<string, CompletionResultTransfer> $matches */
        $matches = [];

        // Alias-based completion: input like "h/htm" resolves alias "h" to its namespace
        $slashPos = strpos($input, '/');
        if ($this->globalEnvironment instanceof GlobalEnvironmentInterface && $slashPos !== false) {
            $aliasPrefix = substr($input, 0, $slashPos);
            $fnPrefix = substr($input, $slashPos + 1);

            $resolvedNs = $this->globalEnvironment->resolveAlias($aliasPrefix);
            if ($resolvedNs !== null) {
                foreach ($this->completeFromNamespaceWithTypes($resolvedNs, $fnPrefix, $aliasPrefix . '/') as $result) {
                    $matches[$result->candidate] = $result;
                }
            }
        }

        // Referred symbol completion and fully qualified completion
        foreach (Phel::getNamespaces() as $namespace) {
            foreach (Phel::getDefinitionInNamespace($namespace) as $name => $definition) {
                $qualifiedName = $namespace === 'phel.core'
                    ? $name
                    : $namespace . '\\' . $name;

                if (str_starts_with($qualifiedName, $input)) {
                    $type = $this->resolveDefinitionType($namespace, $name, $definition);
                    $matches[$qualifiedName] = new CompletionResultTransfer($qualifiedName, $type);
                }
            }
        }

        // Referred symbol names (short names available in current namespace)
        if ($this->globalEnvironment instanceof GlobalEnvironmentInterface) {
            $currentNs = $this->globalEnvironment->getNs();
            foreach ($this->globalEnvironment->getRefers($currentNs) as $name => $sourceNs) {
                if (str_starts_with($name, $input)) {
                    $nsName = $sourceNs->getName();
                    $definition = Phel::getDefinition($nsName, $name);
                    $type = $this->resolveDefinitionType($nsName, $name, $definition);
                    $matches[$name] = new CompletionResultTransfer($name, $type);
                }
            }
        }

        // Special forms and other native symbols (def, fn, let, if, recur,
        // try, throw, ns, ...) are compiler symbols, not registry definitions,
        // so they are absent above. Add any that match and were not already
        // contributed by the registry (real defs keep their richer type).
        foreach ($this->nativeSymbolNames as $name) {
            if (str_starts_with($name, $input) && !isset($matches[$name])) {
                $matches[$name] = new CompletionResultTransfer($name, 'special-form');
            }
        }

        $result = array_values($matches);
        usort($result, static fn(CompletionResultTransfer $a, CompletionResultTransfer $b): int => $a->candidate <=> $b->candidate);

        return $result;
    }

    /**
     * @return list<CompletionResultTransfer>
     */
    private function completeFromNamespaceWithTypes(string $namespace, string $prefix, string $displayPrefix): array
    {
        $matches = [];
        $mungedNs = str_replace('-', '_', $namespace);

        foreach (Phel::getDefinitionInNamespace($mungedNs) as $name => $definition) {
            if (str_starts_with($name, $prefix)) {
                $type = $this->resolveDefinitionType($mungedNs, $name, $definition);
                $matches[] = new CompletionResultTransfer($displayPrefix . $name, $type);
            }
        }

        return $matches;
    }

    /**
     * Determine the type of a Phel definition by inspecting its metadata and value.
     */
    private function resolveDefinitionType(string $namespace, string $name, mixed $definition): string
    {
        $meta = Phel::getDefinitionMetaData($namespace, $name);

        if ($meta instanceof PersistentMapInterface && $meta[Keyword::create('macro')] === true) {
            return 'macro';
        }

        if ($definition instanceof Keyword) {
            return 'keyword';
        }

        if ($definition instanceof FnInterface) {
            return 'function';
        }

        return 'var';
    }
}
