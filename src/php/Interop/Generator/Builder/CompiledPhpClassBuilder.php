<?php

declare(strict_types=1);

namespace Phel\Interop\Generator\Builder;

use Phel\Interop\ReadModel\FunctionToExport;

final class CompiledPhpClassBuilder
{
    private string $prefixNamespace;
    private CompiledPhpMethodBuilder $methodBuilder;

    public function __construct(string $prefixNamespace, CompiledPhpMethodBuilder $methodBuilder)
    {
        $this->prefixNamespace = $prefixNamespace;
        $this->methodBuilder = $methodBuilder;
    }

    public function build(string $phelNs, FunctionToExport ...$functionsToExport): string
    {
        return str_replace([
            '$NAMESPACE$',
            '$CLASS_NAME$',
            '$METHODS$',
        ], [
            $this->buildNamespace($phelNs),
            $this->buildClassName($phelNs),
            $this->buildCompiledPhpMethods($phelNs, ...$functionsToExport),
        ], $this->classTemplate());
    }

    private function buildNamespace(string $phelNs): string
    {
        $normalizedNs = explode('-', str_replace('\\', '-', $phelNs));
        array_pop($normalizedNs);
        $words = ucwords(str_replace('_', ' ', implode('', $normalizedNs)));

        return $this->prefixNamespace . '\\' . str_replace(' ', '', $words);
    }

    private function buildClassName(string $phelNs): string
    {
        $normalizedNs = explode('-', str_replace('\\', '-', $phelNs));
        $className = str_replace('_', '-', array_pop($normalizedNs));

        return $this->dashesToCamelCase($className, true);
    }

    private function buildCompiledPhpMethods(string $phelNs, FunctionToExport ...$functionsToExport): string
    {
        $compiledMethods = '';
        foreach ($functionsToExport as $functionToExport) {
            $compiledMethods .= $this->methodBuilder->build($phelNs, $functionToExport);
        }

        return $compiledMethods;
    }

    private function dashesToCamelCase(string $string, bool $capitalizeFirstCharacter = false): string
    {
        $result = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));

        if (!$capitalizeFirstCharacter) {
            $result[0] = strtolower($result[0]);
        }

        return $result;
    }

    private function classTemplate(): string
    {
        return <<<'TXT'
<?php declare(strict_types=1);

namespace $NAMESPACE$;

use Phel\Interop\PhelCallerTrait;

/**
 * THIS FILE IS AUTO-GENERATED, DO NOT CHANGE ANYTHING IN THIS FILE
 */
final class $CLASS_NAME$
{
    use PhelCallerTrait;
$METHODS$
}
TXT;
    }
}
