<?php

declare(strict_types=1);

namespace Phel\Interop\Generator\Builder;

use Phel\Interop\ReadModel\FunctionToExport;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

final class CompiledPhpMethodBuilder
{
    public function build(string $phelNs, FunctionToExport $functionToExport): string
    {
        $ref = new ReflectionClass($functionToExport->fn());
        $boundTo = $ref->getConstant('BOUND_TO');
        $phelFunctionName = str_replace('_', '-', substr(strrchr($boundTo, '\\'), 1));

        return str_replace([
            '$METHOD_NAME$',
            '$ARGS$',
            '$PHEL_NAMESPACE$',
            '$PHEL_FUNCTION_NAME$',
        ], [
            $this->dashesToCamelCase($phelFunctionName),
            $this->buildArgs($ref->getMethod('__invoke')),
            str_replace(['_', '\\'], ['-', '\\\\'], $phelNs),
            $phelFunctionName,
        ], $this->methodTemplate());
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
        $result = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));

        if (!$capitalizeFirstCharacter) {
            $result[0] = strtolower($result[0]);
        }

        return $result;
    }

    private function methodTemplate(): string
    {
        return <<<'TXT'

    /**
     * @return mixed
     */
    public static function $METHOD_NAME$($ARGS$)
    {
        return self::callPhel('$PHEL_NAMESPACE$', '$PHEL_FUNCTION_NAME$', $ARGS$);
    }

TXT;
    }
}
