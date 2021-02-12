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
        $boundTo = (string)$ref->getConstant('BOUND_TO');

        return str_replace([
            '$METHOD_NAME$',
            '$ARGS$',
            '$PHEL_NAMESPACE$',
            '$PHEL_FUNCTION_NAME$',
        ], [
            $this->buildMethodName($boundTo),
            $this->buildArgs($ref->getMethod('__invoke')),
            str_replace(['_', '\\'], ['-', '\\\\'], $phelNs),
            $this->buildPhelFunctionName($boundTo),
        ], $this->methodTemplate());
    }

    private function buildMethodName(string $boundTo): string
    {
        $words = explode('\\', $boundTo);
        $className = array_pop($words);

        return $this->underscoreToCamelCase($className);
    }

    private function underscoreToCamelCase(string $string): string
    {
        $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));

        return lcfirst($str);
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

    private function buildPhelFunctionName(string $boundTo): string
    {
        return str_replace('_', '-', substr(strrchr($boundTo, '\\'), 1));
    }

    private function methodTemplate(): string
    {
        return <<<'TXT'

    /**
     * @return mixed
     */
    public function $METHOD_NAME$($ARGS$)
    {
        return $this->callPhel('$PHEL_NAMESPACE$', '$PHEL_FUNCTION_NAME$', $ARGS$);
    }

TXT;
    }
}
