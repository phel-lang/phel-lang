<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\Generator\Builder;

use Phel\Interop\Domain\ReadModel\FunctionToExport;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

final class CompiledPhpMethodBuilder
{
    public function build(string $phelNs, FunctionToExport $functionToExport): string
    {
        $ref = new ReflectionClass($functionToExport->fn());
        $boundTo = (string)$ref->getConstant('BOUND_TO');
        $refInvoke = $ref->getMethod('__invoke');

        return str_replace([
            '$METHOD_NAME$',
            '$ARGS$',
            '$RETURN_TYPE$',
            '$PHEL_NAMESPACE$',
            '$PHEL_FUNCTION_NAME$',
        ], [
            $this->buildMethodName($boundTo),
            $this->buildArgs($refInvoke),
            $this->buildReturnType($refInvoke),
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
            $refInvoke->getParameters(),
        );

        return implode(', ', $args);
    }

    private function buildPhelFunctionName(string $boundTo): string
    {
        $suffix = strrchr($boundTo, '\\');

        $functionName = $suffix === false ? $boundTo : substr($suffix, 1);

        return str_replace('_', '-', $functionName);
    }

    private function buildReturnType(ReflectionMethod $refInvoke): string
    {
        $returnType = $refInvoke->getReturnType();

        if ($returnType === null) {
            return ': mixed';
        }

        return ': ' . $returnType->__toString();
    }

    private function methodTemplate(): string
    {
        return <<<'TXT'

    public static function $METHOD_NAME$($ARGS$)$RETURN_TYPE$
    {
        return self::callPhel('$PHEL_NAMESPACE$', '$PHEL_FUNCTION_NAME$', $ARGS$);
    }

TXT;
    }
}
