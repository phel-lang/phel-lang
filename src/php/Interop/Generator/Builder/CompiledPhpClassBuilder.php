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

    /**
     * @param list<FunctionToExport> $functionsToExport
     */
    public function build(string $phelNs, array $functionsToExport): string
    {
        return str_replace([
            '$NAMESPACE$',
            '$CLASS_NAME$',
            '$METHODS$',
        ], [
            $this->buildNamespace($phelNs),
            $this->buildClassName($phelNs),
            $this->buildCompiledPhpMethods($phelNs, $functionsToExport),
        ], $this->classTemplate());
    }

    private function buildNamespace(string $phelNs): string
    {
        $phelNsWords = explode('\\', $phelNs);
        array_pop($phelNsWords);
        $pascalWords = array_map(fn (string $w) => $this->underscoreToPascalCase($w), $phelNsWords);
        $normalizedNamespace = implode('\\', $pascalWords);

        if (empty($this->prefixNamespace)) {
            return $normalizedNamespace;
        }

        return $this->prefixNamespace . '\\' . $normalizedNamespace;
    }

    private function buildClassName(string $phelNs): string
    {
        $words = explode('\\', $phelNs);
        $className = array_pop($words);

        return $this->underscoreToPascalCase($className);
    }

    /**
     * @param list<FunctionToExport> $functionsToExport
     */
    private function buildCompiledPhpMethods(string $phelNs, array $functionsToExport): string
    {
        $compiledMethods = '';
        foreach ($functionsToExport as $functionToExport) {
            $compiledMethods .= $this->methodBuilder->build($phelNs, $functionToExport);
        }

        return $compiledMethods;
    }

    private function underscoreToPascalCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
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
