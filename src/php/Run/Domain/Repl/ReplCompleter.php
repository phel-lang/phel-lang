<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Lang\FnInterface;
use Phel\Lang\Registry;

use function get_declared_classes;
use function get_defined_functions;
use function sprintf;
use function str_starts_with;

final class ReplCompleter
{
    /**
     * @return list<string>
     */
    public function complete(string $input): array
    {
        if (trim($input) === '') {
            return [];
        }

        if (str_starts_with($input, 'php/')) {
            $needle = substr($input, 4);
            $functions = array_filter(
                get_defined_functions()['internal'],
                static fn (string $fn): bool => str_starts_with($fn, $needle),
            );
            $classes = array_filter(
                get_declared_classes(),
                static fn (string $class): bool => str_starts_with($class, $needle),
            );

            $matches = [
                ...array_map(static fn (string $fn): string => 'php/' . $fn, $functions),
                ...array_map(static fn (string $class): string => 'php/' . $class, $classes),
            ];
            sort($matches);

            return $matches;
        }

        return $this->completePhelFunctions($input);
    }

    /**
     * @return list<string>
     */
    private function completePhelFunctions(string $input): array
    {
        $registry = Registry::getInstance();
        $namespaces = $registry->getNamespaces();

        $functions = [];
        foreach ($namespaces as $namespace) {
            $definitions = $registry->getDefinitionInNamespace($namespace);

            foreach ($definitions as $name => $fn) {
                if ($fn instanceof FnInterface) {
                    $functions[] = ($namespace === 'phel\\core')
                        ? $name
                        : sprintf('%s\\%s', $namespace, $name);
                }
            }
        }

        $matches = array_filter(
            $functions,
            static fn (string $function): bool => str_starts_with($function, $input),
        );
        sort($matches);

        return $matches;
    }
}
