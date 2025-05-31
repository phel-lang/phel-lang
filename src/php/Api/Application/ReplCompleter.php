<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Api\Domain\ReplCompleterInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\Registry;

use function get_declared_classes;
use function get_defined_functions;
use function sprintf;
use function str_starts_with;
use function trim;

/**
 * Autocompleter for REPL suggestions in both PHP and Phel contexts.
 */
final readonly class ReplCompleter implements ReplCompleterInterface
{
    /**
     * @param  list<string>  $allNamespaces
     */
    public function __construct(
        private PhelFnLoaderInterface $phelFnLoader,
        private array $allNamespaces = [],
    ) {
    }

    /**
     * Complete input from either PHP or Phel context.
     *
     * @param  string  $input  the input string for which to find completions
     *
     * @return list<string> a list of autocompletion suggestions
     */
    public function complete(string $input): array
    {
        $this->phelFnLoader->loadAllPhelFunctions($this->allNamespaces);

        $input = trim($input);
        if ($input === '') {
            return [];
        }

        return str_starts_with($input, 'php/')
            ? $this->completePhpSymbols(substr($input, 4))
            : $this->completePhelFunctions($input);
    }

    /**
     * Complete native PHP functions and classes.
     *
     * @param  string  $prefix  the input string without the `php/` prefix
     *
     * @return list<string>
     */
    private function completePhpSymbols(string $prefix): array
    {
        $functions = array_filter(
            get_defined_functions()['internal'] ?? [],
            static fn (string $fn): bool => str_starts_with($fn, $prefix),
        );

        $classes = array_filter(
            get_declared_classes(),
            static fn (string $class): bool => str_starts_with($class, $prefix),
        );

        $matches = [
            ...array_map(static fn (string $fn): string => 'php/' . $fn, $functions),
            ...array_map(static fn (string $class): string => 'php/' . $class, $classes),
        ];

        sort($matches);
        return $matches;
    }

    /**
     * Complete available Phel functions.
     *
     * @param  string  $input  the input string to match
     *
     * @return list<string>
     */
    private function completePhelFunctions(string $input): array
    {
        $registry = Registry::getInstance();
        $matches = [];

        foreach ($registry->getNamespaces() as $namespace) {
            foreach ($registry->getDefinitionInNamespace($namespace) as $name => $definition) {
                if (!$definition instanceof FnInterface) {
                    continue;
                }

                $qualifiedName = ($namespace === 'phel\\core') ? $name : sprintf('%s\\%s', $namespace, $name);
                if (str_starts_with($qualifiedName, $input)) {
                    $matches[] = $qualifiedName;
                }
            }
        }

        sort($matches);
        return $matches;
    }
}
