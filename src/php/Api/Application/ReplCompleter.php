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

use function get_declared_classes;
use function get_defined_functions;
use function str_starts_with;
use function trim;

/**
 * Autocompleter for REPL suggestions in both PHP and Phel contexts.
 */
final class ReplCompleter implements ReplCompleterInterface
{
    private static bool $loaded = false;

    /** @var list<string> */
    private static array $phpFunctions = [];

    /** @var list<string> */
    private static array $phpClasses = [];

    /**
     * @param list<string> $allNamespaces
     */
    public function __construct(
        private readonly PhelFnLoaderInterface $phelFnLoader,
        private readonly array $allNamespaces = [],
        private readonly ?GlobalEnvironmentInterface $globalEnvironment = null,
    ) {}

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
        if (!self::$loaded) {
            $this->phelFnLoader->loadAllPhelFunctions($this->allNamespaces);
            self::$loaded = true;
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
        if (self::$phpFunctions === []) {
            self::$phpFunctions = get_defined_functions()['internal'];
        }

        if (self::$phpClasses === []) {
            self::$phpClasses = get_declared_classes();
        }

        $matches = [];

        foreach (self::$phpFunctions as $fn) {
            if (str_starts_with($fn, $prefix)) {
                $matches[] = new CompletionResultTransfer('php/' . $fn, 'php-function');
            }
        }

        foreach (self::$phpClasses as $class) {
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
                $qualifiedName = $namespace === 'phel\\core'
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
