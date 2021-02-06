<?php

declare(strict_types=1);

namespace Phel\Interop\Generator;

use Phel\Interop\ReadModel\Wrapper;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

final class ExportFunctionGenerator
{
    private string $destinyDirectory;

    public function __construct(string $destinyDirectory)
    {
        $this->destinyDirectory = $destinyDirectory;
    }

    public function generateWrapper(string $phelNs, FunctionToExport ...$functionsToExport): Wrapper
    {
        $compiledPhpMethods = $this->buildCompiledPhpMethods($phelNs, ...$functionsToExport);
        $compiledPhpClass = $this->buildCompiledPhpClass($phelNs, $compiledPhpMethods);

        $destiny = sprintf('%s/%s.php', $this->destinyDirectory, $this->buildClassName($phelNs));

        return new Wrapper($destiny, $compiledPhpClass);
    }

    private function buildCompiledPhpMethods(string $phelNs, FunctionToExport ...$functionsToExport): string
    {
        $compiledMethods = '';
        foreach ($functionsToExport as $functionToExport) {
            $ref = new ReflectionClass($functionToExport->fn);
            $boundTo = $ref->getConstant('BOUND_TO');
            $phelFunctionName = str_replace('_', '-', substr(strrchr($boundTo, '\\'), 1));
            $phpMethodName = $this->dashesToCamelCase($phelFunctionName);

            $compiledMethods .= str_replace([
                '$METHOD_NAME$',
                '$ARGS$',
                '$PHEL_NAMESPACE$',
                '$PHEL_FUNCTION_NAME$',
            ], [
                $phpMethodName,
                $this->buildArgs($ref->getMethod('__invoke')),
                $phelNs,
                $phelFunctionName,
            ], $this->methodTemplate());
        }

        return $compiledMethods;
    }

    private function buildArgs(ReflectionMethod $refInvoke): string
    {
        $args = array_map(
            static function (ReflectionParameter $p): string {
                $variadic = $p->isVariadic() ? '...' : '';
                $param = '$' . $p->getName();

                return $variadic . $param;
            },
            $refInvoke->getParameters()
        );


        return implode(', ', $args);
    }

    private function dashesToCamelCase(string $string, bool $capitalizeFirstCharacter = false): string
    {
        $str = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));

        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }

        return $str;
    }

    private function buildCompiledPhpClass(string $phelNs, string $compiledPhpMethods): string
    {
        return str_replace([
            '$NAMESPACE$',
            '$CLASS_NAME$',
            '$METHODS$',
        ], [
            $this->buildNamespace($phelNs),
            $this->buildClassName($phelNs),
            $compiledPhpMethods,
        ], $this->classTemplate());
    }

    private function buildNamespace(string $phelNs): string
    {
        $normalizedNs = explode('-', str_replace('\\', '-', $phelNs));
        array_pop($normalizedNs);

        return 'Generated\\' . str_replace(' ', '\\', ucwords(implode(' ', $normalizedNs)));
    }

    private function buildClassName(string $phelNs): string
    {
        $normalizedNs = explode('-', str_replace('\\', '-', $phelNs));
        $className = str_replace('_', '-', array_pop($normalizedNs));

        return $this->dashesToCamelCase($className, true);
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

    private function methodTemplate(): string
    {
        return <<<'TXT'

    /**
     * @return mixed
     */
    public function $METHOD_NAME$($ARGS$)
    {
        return self::callPhel('$PHEL_NAMESPACE$', '$PHEL_FUNCTION_NAME$', $ARGS$);
    }

TXT;
    }
}
